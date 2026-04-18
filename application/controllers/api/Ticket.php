<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket API Controller — View only (GET).
 *
 * Endpoints:
 * - GET /api/ticket        → index_get()  — List ticket + filter + paginate
 * - GET /api/ticket/{id}   → show_get($id) — Detail ticket + timeline + supporting + attachments
 */
class Ticket extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('TicketService');
    }

    /**
     * GET /api/ticket
     *
     * Query params: date_start, date_end, department, feedback, page, per_page
     *
     * @return void
     */
    public function index_get()
    {
        $filters = [
            'date_start' => $this->input->get('date_start'),
            'date_end'   => $this->input->get('date_end'),
            'department' => $this->input->get('department'),
            'feedback'   => $this->input->get('feedback'),
            'page'       => $this->input->get('page') ?: 1,
            'per_page'   => $this->input->get('per_page') ?: 10,
        ];

        $result = $this->ticketservice->getList($filters);

        $this->respondPaginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * GET /api/ticket/{id}
     *
     * @param  int  $id Ticket ID
     * @return void
     */
    public function show_get($id)
    {
        $detail = $this->ticketservice->getDetail($id);

        if (!$detail) {
            $this->respondError('Ticket tidak ditemukan', 404);
            return;
        }

        $this->respond($detail, 'Data berhasil diambil');
    }
}
