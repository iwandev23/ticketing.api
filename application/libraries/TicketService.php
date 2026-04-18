<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TicketService — Business logic untuk ticket.
 *
 * Tidak ada query langsung di sini, semua lewat model.
 */
class TicketService
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Ticket_model');
        $this->CI->load->model('Ticket_feedback_model');
    }

    /**
     * Ambil list ticket dengan filter dan pagination.
     *
     * @param  array $filters Associative array filter (date_start, date_end, department, feedback, page, per_page)
     * @return array ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 10]
     */
    public function getList($filters = [])
    {
        $page    = isset($filters['page']) ? (int) $filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        if ($page < 1) $page = 1;
        if ($perPage < 1) $perPage = 10;

        $result = $this->CI->Ticket_model->getList($filters, $page, $perPage);

        return [
            'data'     => $result['data'],
            'total'    => $result['total'],
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Ambil detail ticket lengkap.
     *
     * Includes: ticket data, timeline, supporting info, attachments, feedback.
     *
     * @param  int        $id Ticket ID
     * @return array|null Detail lengkap atau null jika tidak ditemukan
     */
    public function getDetail($id)
    {
        $ticket = $this->CI->Ticket_model->getById($id);

        if (!$ticket) {
            return null;
        }

        // ── Build timeline (data-driven via TimelineService) ────────────
        $this->CI->load->library('TimelineService');
        $ticket['timeline'] = $this->CI->timelineservice->buildTimeline($id);

        // ── Can finish check ────────────────────────────────────────────
        $finishCheck = $this->CI->timelineservice->canFinish($id);
        $ticket['can_finish']    = $finishCheck['can_finish'];
        $ticket['finish_reason'] = $finishCheck['reason'];

        // ── Get supporting ──────────────────────────────────────────────
        $ticket['supporting'] = $this->getSupporting($id, $ticket['department']);

        // ── Get attachments (dari ticket_attachment, linked via ticket_id = ticket.id) ──
        $attachments = $this->CI->db
            ->where('ticket_id', $id)
            ->get('ticket_attachment')
            ->result_array();

        $ticket['attachments'] = array_map(function ($att) {
            return [
                'id'             => $att['id'],
                'file_url'       => $att['file_url'],
                'date'           => $att['created_at'],
                'created_byname' => $att['created_byname'],
            ];
        }, $attachments);

        // ── Get latest feedback ─────────────────────────────────────────
        $feedback = $this->CI->Ticket_feedback_model->getLatestByTicketId($id);
        $ticket['feedback'] = $feedback ? [
            'id'       => isset($feedback['ticket_id']) ? $feedback['ticket_id'] : null,
            'analysis' => isset($feedback['analysis']) ? $feedback['analysis'] : null,
            'action'   => isset($feedback['action']) ? $feedback['action'] : null,
            'results'  => isset($feedback['results']) ? $feedback['results'] : null,
            'date'     => isset($feedback['created_at']) ? $feedback['created_at'] : null,
        ] : [
            'id'       => null,
            'analysis' => null,
            'action'   => null,
            'results'  => null,
            'date'     => null,
        ];

        return $ticket;
    }

    /**
     * Build timeline 4-step dari status ticket.
     *
     * @deprecated Gunakan TimelineService::buildTimeline() yang data-driven.
     *             Method ini dipertahankan sebagai fallback.
     *
     * @param  array $ticket Data ticket
     * @return array Array of 4 timeline steps
     */
    protected function buildTimeline($ticket)
    {
        $status = isset($ticket['status']) ? $ticket['status'] : '';

        return [
            [
                'label'     => 'Tiket Dibuat',
                'timestamp' => isset($ticket['created_at']) ? $ticket['created_at'] : null,
                'is_done'   => true,
            ],
            [
                'label'     => 'Diterima oleh ' . (isset($ticket['department']) ? $ticket['department'] : ''),
                'timestamp' => isset($ticket['date']) ? $ticket['date'] : null,
                'is_done'   => ($status !== 'Open'),
            ],
            [
                'label'     => 'Menunggu Feedback',
                'timestamp' => null,
                'is_done'   => in_array($status, ['Feedback', 'Done']),
            ],
            [
                'label'     => 'Selesai',
                'timestamp' => null,
                'is_done'   => ($status === 'Done'),
            ],
        ];
    }

    /**
     * Ambil data supporting dari feedback terbaru.
     *
     * Return { name, department } atau null jika belum ada feedback.
     *
     * @param  int        $ticketId   Ticket ID
     * @param  string     $ticketDept Departemen tujuan tiket (untuk fallback)
     * @return array|null Supporting data
     */
    protected function getSupporting($ticketId, $ticketDept = null)
    {
        $feedback = $this->CI->Ticket_feedback_model->getLatestByTicketId($ticketId);

        if (!$feedback) {
            return null;
        }

        return [
            'name'       => isset($feedback['created_byname']) ? $feedback['created_byname'] : null,
            'department' => isset($feedback['department']) ? $feedback['department'] : $ticketDept,
        ];
    }
}
