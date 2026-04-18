<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_department API Controller — View only (GET).
 *
 * Endpoints:
 * - GET /api/ticket-department        → index_get()  — List departemen
 * - GET /api/ticket-department/{id}   → show_get($id) — Detail departemen
 */
class Ticket_department extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_department_model');
    }

    /**
     * GET /api/ticket-department
     *
     * @return void
     */
    public function index_get()
    {
        $data = $this->Ticket_department_model->getAll();

        $this->respond($data, 'Data berhasil diambil');
    }

    /**
     * GET /api/ticket-department/{id}
     *
     * @param  int  $id Department ID
     * @return void
     */
    public function show_get($id)
    {
        $department = $this->Ticket_department_model->getById($id);

        if (!$department) {
            $this->respondError('Departemen tidak ditemukan', 404);
            return;
        }

        $this->respond($department, 'Data berhasil diambil');
    }
}
