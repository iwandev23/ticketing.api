<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_status API Controller — Full CRUD.
 *
 * Endpoints:
 * - GET    /api/ticket-status        → index_get()
 * - GET    /api/ticket-status/{id}   → show_get($id)
 * - POST   /api/ticket-status        → create_post()
 * - PUT    /api/ticket-status/{id}   → update_put($id)
 * - DELETE /api/ticket-status/{id}   → delete_delete($id)
 */
class Ticket_status extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_status_model');
    }

    /**
     * GET /api/ticket-status
     *
     * Data di-ORDER BY id ASC untuk step bar di FE.
     *
     * @return void
     */
    public function index_get()
    {
        $filters = [
            'department' => $this->input->get('department'),
        ];
        $page    = $this->input->get('page') ?: 1;
        $perPage = $this->input->get('per_page') ?: 10;

        $result = $this->Ticket_status_model->getAll($filters, $page, $perPage);

        $this->respondPaginated($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/ticket-status/{id}
     *
     * @param  int  $id Status ID
     * @return void
     */
    public function show_get($id)
    {
        $status = $this->Ticket_status_model->getById($id);

        if (!$status) {
            $this->respondError('Status tidak ditemukan', 404);
            return;
        }

        $this->respond($status, 'Data berhasil diambil');
    }

    public function create_post()
    {
        $data = $this->getJsonInput();

        $errors = [];
        $statusStr = isset($data['status']) ? $data['status'] : (isset($data['description']) ? $data['description'] : (isset($data['name']) ? $data['name'] : null));
        
        if (empty($statusStr)) {
            $errors[] = 'status (atau description/name) wajib diisi';
        }

        if (!empty($errors)) {
            $this->respondError('Validasi gagal', 422, $errors);
            return;
        }

        $insertData = [
            'status' => $statusStr
        ];
        
        if (isset($data['status_id'])) {
            $insertData['status_id'] = $data['status_id'];
        }

        $id = $this->Ticket_status_model->insert($insertData);
        $id = $id ? $id : (isset($insertData['status_id']) ? $insertData['status_id'] : 'success');

        $this->respond(['id' => $id], 'Status berhasil ditambahkan', true, 201);
    }

    public function update_put($id)
    {
        $statusRec = $this->Ticket_status_model->getById($id);

        if (!$statusRec) {
            $this->respondError('Status tidak ditemukan', 404);
            return;
        }

        $data = $this->getJsonInput();

        $updateData = [];
        $statusStr = isset($data['status']) ? $data['status'] : (isset($data['description']) ? $data['description'] : (isset($data['name']) ? $data['name'] : null));
        if ($statusStr !== null) {
            $updateData['status'] = $statusStr;
        }

        if (empty($updateData)) {
            $this->respondError('Tidak ada data untuk diupdate', 422);
            return;
        }

        $this->Ticket_status_model->update($id, $updateData);

        $this->respond(null, 'Status berhasil diperbarui');
    }

    /**
     * DELETE /api/ticket-status/{id}
     *
     * @param  int  $id Status ID
     * @return void
     */
    public function delete_delete($id)
    {
        $status = $this->Ticket_status_model->getById($id);

        if (!$status) {
            $this->respondError('Status tidak ditemukan', 404);
            return;
        }

        $this->Ticket_status_model->softDelete($id);

        $this->respond(null, 'Status berhasil dihapus');
    }
}
