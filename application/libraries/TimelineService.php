<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TimelineService — Business logic untuk timeline aktivitas + finish mechanism.
 *
 * GATEWAY UTAMA: Semua perubahan tracking WAJIB lewat logEvent().
 * logEvent() akan auto-sync ticket.status berdasarkan $statusMap.
 *
 * Mapping tracking_id → ticket.status:
 *   1 (Dibuat)     → 1 (Open)
 *   2 (Diterima)   → 1 (Open, tetap)
 *   3 (Feedback)   → 3 (Feedback)
 *   4 (Finish)     → 2 (Done)
 *   5 (Dibatalkan) → 4 (Cancel)
 */
class TimelineService
{
    /** @var CI_Controller */
    protected $CI;

    /**
     * Correlation map: tracking_id → ticket_status.status_id
     * Setiap kali logEvent() dipanggil, ticket.status di-sync ke nilai ini.
     *
     * @var array
     */
    protected $statusMap = [
        1 => 1,  // Dibuat     → Open
        2 => 1,  // Diterima   → Open (tetap)
        3 => 3,  // Feedback   → Feedback
        4 => 2,  // Finish     → Done
        5 => 4,  // Dibatalkan → Cancel
    ];

    /** @var int tracking_id untuk step Finish */
    const TRACKING_FINISH = 4;

    /** @var int tracking_id untuk step Dibatalkan */
    const TRACKING_CANCEL = 5;

    /** @var int Minimum jam sejak feedback untuk boleh finish */
    const FINISH_HOURS_THRESHOLD = 24;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Ticket_tracking_model');
        $this->CI->load->model('Ticket_history_model');
        $this->CI->load->model('Ticket_model');
        $this->CI->load->model('Ticket_feedback_model');
    }

    // ====================================================================
    // Build Timeline
    // ====================================================================

    /**
     * Build timeline lengkap: merge ticket_tracking (semua step) + ticket_history (completed).
     *
     * Logic:
     * 1. Ambil semua active steps dari ticket_tracking
     * 2. Ambil history entries untuk ticket ini
     * 3. Merge: step yang ada di history → is_done=true + timestamp
     * 4. Step "Dibatalkan" (id=5) hanya muncul jika memang ada di history
     *
     * @param  string $ticketId Ticket ID
     * @return array  Array of timeline steps [{tracking_id, label, timestamp, is_done, description}]
     */
    public function buildTimeline($ticketId)
    {
        $steps   = $this->CI->Ticket_tracking_model->getActiveSteps();
        $history = $this->CI->Ticket_history_model->getByTicketId($ticketId);

        // Index history by tracking_id untuk lookup cepat
        $historyMap = [];
        foreach ($history as $entry) {
            // Ambil entry pertama per tracking_id (kronologis, sudah ORDER BY date ASC)
            if (!isset($historyMap[$entry['tracking_id']])) {
                $historyMap[$entry['tracking_id']] = $entry;
            }
        }

        $timeline = [];
        foreach ($steps as $step) {
            $trackingId = (int) $step['id'];

            // Skip step "Dibatalkan" jika tidak ada di history
            if ($trackingId === self::TRACKING_CANCEL && !isset($historyMap[$trackingId])) {
                continue;
            }

            $isDone    = isset($historyMap[$trackingId]);
            $timestamp = $isDone ? $historyMap[$trackingId]['date'] : null;
            $desc      = $isDone ? $historyMap[$trackingId]['description'] : null;
            $updatedByName = $isDone ? (isset($historyMap[$trackingId]['updated_byname']) ? $historyMap[$trackingId]['updated_byname'] : $historyMap[$trackingId]['updateby']) : null;

            $timeline[] = [
                'tracking_id'    => $trackingId,
                'label'          => $step['name'],
                'timestamp'      => $timestamp,
                'is_done'        => $isDone,
                'description'    => $desc,
                'updated_byname' => $updatedByName,
            ];
        }

        return $timeline;
    }

    // ====================================================================
    // Log Event (GATEWAY)
    // ====================================================================

    /**
     * Log event ke ticket_history + auto-sync ticket.status.
     *
     * INI ADALAH SATU-SATUNYA PINTU untuk perubahan tracking.
     * Dalam satu transaksi DB:
     *   1. INSERT ticket_history
     *   2. UPDATE ticket.status sesuai $statusMap
     *
     * @param  string $ticketId    Ticket ID
     * @param  int    $trackingId  Tracking step ID (FK ticket_tracking.id)
     * @param  string $description Catatan/keterangan
     * @param  string $updatedBy   Siapa yang melakukan perubahan (ID)
     * @param  string $updatedByName Nama yang melakukan perubahan
     * @return int    Insert ID dari ticket_history
     * @throws Exception Jika transaksi gagal
     */
    public function logEvent($ticketId, $trackingId, $description, $updatedBy, $updatedByName = null)
    {
        $this->CI->db->trans_start();

        // ── 1. INSERT ke ticket_history ─────────────────────────────────
        $historyId = $this->CI->Ticket_history_model->insert([
            'ticket_id'      => $ticketId,
            'tracking_id'    => $trackingId,
            'date'           => date('Y-m-d H:i:s'),
            'description'    => $description,
            'updatedate'     => date('Y-m-d H:i:s'),
            'updateby'       => $updatedBy,
            'updated_byname' => $updatedByName,
            'status'         => 1,
        ]);

        // ── 2. AUTO-SYNC ticket.status ──────────────────────────────────
        if (isset($this->statusMap[$trackingId])) {
            $newStatus = $this->statusMap[$trackingId];
            $this->CI->db
                ->where('id', $ticketId)
                ->update('ticket', ['status' => $newStatus]);
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === FALSE) {
            throw new Exception('Gagal menyimpan event timeline');
        }

        return $historyId;
    }

    // ====================================================================
    // Finish Mechanism
    // ====================================================================

    /**
     * Check apakah ticket eligible untuk di-finish.
     *
     * Rules:
     * 1. ticket.status != 2 (belum Done)
     * 2. Ada feedback (ticket_feedback)
     * 3. feedback.created_at sudah >= 24 jam yang lalu
     *
     * @param  string $ticketId Ticket ID
     * @return array  ['can_finish'=>bool, 'reason'=>string, 'feedback_at'=>string|null, 'hours_since_feedback'=>float|null]
     */
    public function canFinish($ticketId)
    {
        // ── 1. Ambil ticket ─────────────────────────────────────────────
        $ticket = $this->CI->Ticket_model->getById($ticketId);

        if (!$ticket) {
            return [
                'can_finish'           => false,
                'reason'               => 'Ticket tidak ditemukan',
                'feedback_at'          => null,
                'hours_since_feedback' => null,
            ];
        }

        // ── 2. Cek apakah sudah Done ────────────────────────────────────
        if ((int) $ticket['status'] === 2) {
            return [
                'can_finish'           => false,
                'reason'               => 'Ticket sudah selesai (Done)',
                'feedback_at'          => null,
                'hours_since_feedback' => null,
            ];
        }

        // ── 3. Cek apakah sudah pernah Finish di history ────────────────
        $alreadyFinished = $this->CI->Ticket_history_model->hasTrackingStep($ticketId, self::TRACKING_FINISH);
        if ($alreadyFinished) {
            return [
                'can_finish'           => false,
                'reason'               => 'Ticket sudah pernah di-finish',
                'feedback_at'          => null,
                'hours_since_feedback' => null,
            ];
        }

        // ── 4. Cek apakah ada feedback ──────────────────────────────────
        $feedback = $this->CI->Ticket_feedback_model->getLatestByTicketId($ticketId);

        if (!$feedback) {
            return [
                'can_finish'           => false,
                'reason'               => 'Belum ada feedback',
                'feedback_at'          => null,
                'hours_since_feedback' => null,
            ];
        }

        // ── 5. Hitung selisih waktu feedback ────────────────────────────
        $feedbackAt  = strtotime($feedback['created_at']);
        $now         = time();
        $diffSeconds = $now - $feedbackAt;
        $diffHours   = round($diffSeconds / 3600, 1);

        if ($diffHours < self::FINISH_HOURS_THRESHOLD) {
            return [
                'can_finish'           => false,
                'reason'               => 'Belum mencapai ' . self::FINISH_HOURS_THRESHOLD . ' jam sejak feedback terakhir (baru ' . $diffHours . ' jam)',
                'feedback_at'          => $feedback['created_at'],
                'hours_since_feedback' => $diffHours,
            ];
        }

        // ── 6. Eligible ─────────────────────────────────────────────────
        return [
            'can_finish'           => true,
            'reason'               => 'Eligible — feedback telah melebihi 1x24 jam',
            'feedback_at'          => $feedback['created_at'],
            'hours_since_feedback' => $diffHours,
        ];
    }

    /**
     * Execute finish ticket.
     *
     * Memanggil logEvent() dengan tracking_id=4 (Finish),
     * yang akan auto-sync ticket.status ke 2 (Done).
     *
     * @param  string $ticketId  Ticket ID
     * @param  string $updatedBy Siapa yang finish
     * @param  string $updatedByName Nama yang finish
     * @return array  ['ticket_id', 'history_id', 'finished_at', 'status_synced_to']
     * @throws Exception Jika tidak eligible
     */
    public function finishTicket($ticketId, $updatedBy, $updatedByName = null)
    {
        // Validasi eligibility
        $check = $this->canFinish($ticketId);

        if (!$check['can_finish']) {
            throw new Exception($check['reason']);
        }

        // Execute finish via logEvent (auto-sync status)
        $finishedAt = date('Y-m-d H:i:s');
        $historyId = $this->logEvent(
            $ticketId,
            self::TRACKING_FINISH,
            'Ticket selesai — di-finish oleh ' . ($updatedByName ?: $updatedBy),
            $updatedBy,
            $updatedByName
        );

        return [
            'ticket_id'       => $ticketId,
            'history_id'      => $historyId,
            'finished_at'     => $finishedAt,
            'status_synced_to' => $this->statusMap[self::TRACKING_FINISH],
        ];
    }

    /**
     * Scheduler: auto-finish semua ticket yang eligible.
     *
     * Logic:
     * - Cari ticket dengan status=3 (Feedback), feedback.created_at >= 24 jam,
     *   dan belum pernah Finish di history.
     * - Loop → finishTicket() per ticket.
     *
     * @return array ['finished_count'=>int, 'ticket_ids'=>[], 'errors'=>[]]
     */
    public function autoFinishEligibleTickets()
    {
        // Query: ticket yang eligible untuk auto-finish
        $query = $this->CI->db->query("
            SELECT t.id
            FROM ticket t
            INNER JOIN ticket_feedback tf ON t.id = tf.ticket_id
            WHERE t.status = 3
              AND t.deleted_at IS NULL
              AND tf.created_at <= DATE_SUB(NOW(), INTERVAL " . self::FINISH_HOURS_THRESHOLD . " HOUR)
              AND NOT EXISTS (
                SELECT 1 FROM ticket_history th
                WHERE th.ticket_id = t.id
                  AND th.tracking_id = " . self::TRACKING_FINISH . "
                  AND th.status = 1
              )
        ");

        $eligible   = $query->result_array();
        $finished   = [];
        $errors     = [];

        foreach ($eligible as $row) {
            try {
                $this->finishTicket($row['id'], 'system', 'System Scheduler');
                $finished[] = $row['id'];
            } catch (Exception $e) {
                $errors[] = [
                    'ticket_id' => $row['id'],
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return [
            'finished_count' => count($finished),
            'ticket_ids'     => $finished,
            'errors'         => $errors,
        ];
    }
}
