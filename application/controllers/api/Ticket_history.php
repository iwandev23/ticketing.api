<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_history API Controller — Timeline + Finish operations.
 *
 * Endpoints:
 * - GET  /api/ticket-history?ticket_id=X          → index_get()       — Timeline per ticket
 * - POST /api/ticket-history                       → create_post()     — Manual log event
 * - GET  /api/ticket-history/can-finish?ticket_id=X→ can_finish_get()  — Check finish eligibility
 * - POST /api/ticket-history/finish                → finish_post()     — Manual finish button
 */
class Ticket_history extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_history_model');
        $this->load->library('TimelineService');
    }

    /**
     * GET /api/ticket-history?ticket_id=X
     *
     * Ambil timeline aktivitas per ticket.
     * Jika ticket_id tidak disediakan → return error.
     *
     * @return void
     */
    public function index_get()
    {
        $ticketId = $this->input->get('ticket_id');

        if (empty($ticketId)) {
            $this->respondError('Parameter ticket_id wajib diisi', 422);
            return;
        }

        $history = $this->Ticket_history_model->getByTicketId($ticketId);

        $this->respond($history, 'Data berhasil diambil');
    }

    /**
     * POST /api/ticket-history
     *
     * Manual log event ke timeline.
     * Required: ticket_id, tracking_id
     * Optional: description, updateby
     *
     * Menggunakan TimelineService::logEvent() → auto-sync ticket.status.
     *
     * @return void
     */
    public function create_post()
    {
        $data = $this->getJsonInput();

        // Validasi
        $errors = [];
        if (empty($data['ticket_id']))  $errors[] = 'ticket_id wajib diisi';
        if (empty($data['tracking_id'])) $errors[] = 'tracking_id wajib diisi';

        if (!empty($errors)) {
            $this->respondError('Validasi gagal', 422, $errors);
            return;
        }

        try {
            $historyId = $this->timelineservice->logEvent(
                $data['ticket_id'],
                (int) $data['tracking_id'],
                isset($data['description']) ? $data['description'] : '',
                isset($data['updateby']) ? $data['updateby'] : 'system'
            );

            $this->respond(
                ['id' => $historyId],
                'Event berhasil dicatat',
                true,
                201
            );
        } catch (Exception $e) {
            $this->respondError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/ticket-history/can-finish?ticket_id=X
     *
     * Check apakah ticket eligible untuk di-finish.
     * FE menggunakan endpoint ini untuk show/hide tombol Finish.
     *
     * @return void
     */
    public function can_finish_get()
    {
        $ticketId = $this->input->get('ticket_id');

        if (empty($ticketId)) {
            $this->respondError('Parameter ticket_id wajib diisi', 422);
            return;
        }

        $result = $this->timelineservice->canFinish($ticketId);

        $this->respond($result, 'Data berhasil diambil');
    }

    /**
     * POST /api/ticket-history/finish
     *
     * Tombol manual finish. Validasi 24-jam rule dilakukan di TimelineService.
     *
     * Request body: { "ticket_id": "...", "updateby": "..." }
     *
     * @return void
     */
    public function finish_post()
    {
        $data = $this->getJsonInput();

        if (empty($data['ticket_id'])) {
            $this->respondError('ticket_id wajib diisi', 422);
            return;
        }

        $updatedBy = isset($data['updateby']) ? $data['updateby'] : 'system';

        try {
            $result = $this->timelineservice->finishTicket($data['ticket_id'], $updatedBy);

            $this->respond($result, 'Ticket berhasil di-finish');
        } catch (Exception $e) {
            $this->respondError($e->getMessage(), 422);
        }
    }
}
