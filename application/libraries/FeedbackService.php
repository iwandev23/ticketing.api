<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * FeedbackService — Business logic untuk submit feedback.
 *
 * Submit feedback akan:
 * 1. Validasi required fields
 * 2. Cek ticket exist
 * 3. Validasi files (opsional) — tipe dan ukuran
 * 4. INSERT ke ticket_feedback (dalam transaksi)
 * 5. Upload file + INSERT ke ticket_attachment (jika ada)
 * 6. UPDATE ticket status → 'Feedback'
 * 7. Transaksi selesai (atau rollback jika gagal)
 */
class FeedbackService
{
    /** @var CI_Controller */
    protected $CI;

    /** @var array Tipe file yang diizinkan */
    protected $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

    /** @var int Max file size dalam KB (10MB = 10240 KB) */
    protected $maxSizeKB = 10240;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Ticket_model');
        $this->CI->load->model('Ticket_feedback_model');
    }

    /**
     * Submit feedback baru, upload attachment (opsional), dan update status ticket.
     *
     * @param  array $data  Data feedback dari request (form fields)
     * @param  array $files Array of individual file arrays (sudah dinormalisasi oleh controller)
     *                      Setiap elemen: ['name'=>..., 'type'=>..., 'tmp_name'=>..., 'error'=>..., 'size'=>...]
     * @return array ['feedback' => [...], 'attachments' => [...], 'ticket_status_updated_to' => 'Feedback']
     * @throws Exception Jika validasi gagal, ticket tidak ditemukan, atau transaksi gagal
     */
    public function submit($data, $files = [])
    {
        // ── 1. Validasi required fields ─────────────────────────────────
        $required = ['action', 'department', 'ticket_id'];
        $missing  = [];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Field wajib belum diisi: ' . implode(', ', $missing));
        }

        // ── 2. Cek ticket exist (deleted_at IS NULL) ────────────────────
        $ticket = $this->CI->Ticket_model->getById($data['ticket_id']);

        if (!$ticket) {
            throw new Exception('Ticket tidak ditemukan atau sudah dihapus');
        }

        // ── 2a. Cek apakah feedback sudah ada (karena 1:1) ───────────────
        $existingFeedback = $this->CI->Ticket_feedback_model->getLatestByTicketId($data['ticket_id']);
        if ($existingFeedback) {
            throw new Exception('Feedback untuk tiket ini sudah ada. Gunakan metode Update (PUT) untuk merubahnya.');
        }

        // ── 3. Validasi files[] sebelum transaksi ───────────────────────
        $maxSizeBytes = $this->maxSizeKB * 1024;

        if (!empty($files)) {
            foreach ($files as $i => $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Terjadi error saat upload file: ' . $file['name']);
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $this->allowedTypes)) {
                    throw new Exception('Tipe file tidak diizinkan (' . $ext . '). Hanya: ' . implode(', ', $this->allowedTypes));
                }

                if ($file['size'] > $maxSizeBytes) {
                    throw new Exception('Ukuran file ' . $file['name'] . ' melebihi batas maksimal 10MB');
                }
            }
        }

        // ── 4. Start transaksi ──────────────────────────────────────────
        $this->CI->db->trans_start();

        // ── 5. INSERT ke ticket_feedback ─────────────────────────────────
        $feedbackData = [
            'ticket_id'      => $data['ticket_id'],
            'analysis'       => isset($data['analysis']) ? $data['analysis'] : null,
            'action'         => $data['action'],
            'results'        => isset($data['results']) ? $data['results'] : null,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'created_by'     => isset($data['created_by']) ? $data['created_by'] : null,
            'created_byname' => isset($data['created_byname']) ? $data['created_byname'] : null,
        ];

        $feedbackId = $this->CI->Ticket_feedback_model->insert($feedbackData);

        // ── 5a. UPDATE kategori ticket di tabel ticket (opsional) ───────
        if (!empty($data['category_id'])) {
            $this->CI->db->where('id', $data['ticket_id'])
                         ->update('ticket', ['category_id' => $data['category_id']]);
        }

        // ── 6. Upload files + INSERT ke ticket_attachment ────────────────
        $uploadedFiles = [];

        if (!empty($files)) {
            $year      = date('Y');
            $month     = date('m');
            $uploadDir = FCPATH . 'uploads/attachments/' . $year . '/' . $month . '/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($files as $file) {
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileName = time() . '_' . uniqid() . '.' . $ext;
                $filePath = $uploadDir . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $this->CI->db->trans_rollback();
                    throw new Exception('Gagal menyimpan file ' . $file['name'] . ' ke server');
                }

                $relativePath = 'uploads/attachments/' . $year . '/' . $month . '/' . $fileName;

                // INSERT ke ticket_attachment
                $attachmentId = substr(time() . rand(1000, 9999), 0, 15);
                $attachmentData = [
                    'id'             => $attachmentId,
                    'ticket_id'      => $data['ticket_id'],
                    'file_url'       => $relativePath,
                    'created_at'     => date('Y-m-d H:i:s'),
                    'created_by'     => isset($data['created_by']) ? substr($data['created_by'], 0, 7) : null,
                    'created_byname' => isset($data['created_byname']) ? $data['created_byname'] : null,
                ];

                $this->CI->db->insert('ticket_attachment', $attachmentData);
                $attId = $attachmentId;

                $uploadedFiles[] = [
                    'id'            => $attId,
                    'file_name'     => $fileName,
                    'original_name' => $file['name'],
                    'file_path'     => $relativePath,
                ];
            }
        }

        // ── 7. Log ke timeline + auto-sync ticket.status ───────────────
        // Semua perubahan status WAJIB lewat TimelineService::logEvent()
        // tracking_id=3 (Feedback) → auto-sync ticket.status=3
        $this->CI->load->library('TimelineService');
        $this->CI->timelineservice->logEvent(
            $data['ticket_id'],
            3, // tracking_id = Feedback
            'Feedback action taken: ' . mb_substr($data['action'], 0, 100),
            isset($data['created_by']) ? $data['created_by'] : 'system',
            isset($data['created_byname']) ? $data['created_byname'] : 'System'
        );

        // ── 8. Complete transaksi ───────────────────────────────────────
        $this->CI->db->trans_complete();

        // ── 9. Cek apakah transaksi berhasil ────────────────────────────
        if ($this->CI->db->trans_status() === FALSE) {
            throw new Exception('Transaksi gagal, feedback tidak disimpan');
        }

        // ── 10. Return data ─────────────────────────────────────────────
        return [
            'feedback' => [
                'id'             => $feedbackId,
                'action'         => $feedbackData['action'],
                'results'        => $feedbackData['results'],
                'date'           => $feedbackData['created_at'],
                'created_byname' => $feedbackData['created_byname'],
            ],
            'attachments'              => $uploadedFiles,
            'ticket_status_updated_to' => 'Feedback',
            'ticket_category_updated_to' => !empty($data['category_id']) ? $data['category_id'] : null,
        ];
    }
}
