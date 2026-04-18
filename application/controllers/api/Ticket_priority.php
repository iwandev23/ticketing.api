<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_priority API Controller — Full CRUD.
 *
 * Endpoints:
 * - GET    /api/ticket-priority        → index_get()
 * - GET    /api/ticket-priority/{id}   → show_get($id)
 * - POST   /api/ticket-priority        → create_post()
 * - PUT    /api/ticket-priority/{id}   → update_put($id)
 * - DELETE /api/ticket-priority/{id}   → delete_delete($id)
 */
class Ticket_priority extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_priority_model');
    }

    /**
     * GET /api/ticket-priority
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

        $result = $this->Ticket_priority_model->getAll($filters, $page, $perPage);

        $this->respondPaginated($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/ticket-priority/{id}
     *
     * @param  int  $id Priority ID
     * @return void
     */
    public function show_get($id)
    {
        $priority = $this->Ticket_priority_model->getById($id);

        if (!$priority) {
            $this->respondError('Priority tidak ditemukan', 404);
            return;
        }

        $this->respond($priority, 'Data berhasil diambil');
    }

    public function create_post()
    {
        $data = $this->getJsonInput();

        $errors = [];
        $priorityName = isset($data['priority_name']) ? $data['priority_name'] : (isset($data['description']) ? $data['description'] : null);
        
        if (empty($priorityName)) {
            $errors[] = 'priority_name (atau description) wajib diisi';
        }

        if (!empty($errors)) {
            $this->respondError('Validasi gagal', 422, $errors);
            return;
        }

        $insertData = [
            'priority_name' => $priorityName
        ];
        
        if (isset($data['priority_id'])) {
            $insertData['priority_id'] = $data['priority_id'];
        }

        $id = $this->Ticket_priority_model->insert($insertData);

        // Jika insert_id kosong (karena bukan AI), kembalikan data yang dikirim
        $id = $id ? $id : (isset($insertData['priority_id']) ? $insertData['priority_id'] : 'success');

        $this->respond(['id' => $id], 'Priority berhasil ditambahkan', true, 201);
    }

    public function update_put($id)
    {
        $priority = $this->Ticket_priority_model->getById($id);

        if (!$priority) {
            $this->respondError('Priority tidak ditemukan', 404);
            return;
        }

        $data = $this->getJsonInput();

        $updateData = [];
        if (isset($data['priority_name']) || isset($data['description'])) {
            $updateData['priority_name'] = isset($data['priority_name']) ? $data['priority_name'] : $data['description'];
        }

        if (empty($updateData)) {
            $this->respondError('Tidak ada data untuk diupdate', 422);
            return;
        }

        $this->Ticket_priority_model->update($id, $updateData);

        $this->respond(null, 'Priority berhasil diperbarui');
    }

    /**
     * DELETE /api/ticket-priority/{id}
     *
     * @param  int  $id Priority ID
     * @return void
     */
    public function delete_delete($id)
    {
        $priority = $this->Ticket_priority_model->getById($id);

        if (!$priority) {
            $this->respondError('Priority tidak ditemukan', 404);
            return;
        }

        $this->Ticket_priority_model->softDelete($id);

        $this->respond(null, 'Priority berhasil dihapus');
    }
}
