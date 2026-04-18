<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_category API Controller — Full CRUD.
 *
 * Endpoints:
 * - GET    /api/ticket-category        → index_get()
 * - GET    /api/ticket-category/{id}   → show_get($id)
 * - POST   /api/ticket-category        → create_post()
 * - PUT    /api/ticket-category/{id}   → update_put($id)
 * - DELETE /api/ticket-category/{id}   → delete_delete($id)
 */
class Ticket_category extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_category_model');
    }

    /**
     * GET /api/ticket-category
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

        $result = $this->Ticket_category_model->getAll($filters, $page, $perPage);

        $this->respondPaginated($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/ticket-category/{id}
     *
     * @param  int  $id Category ID
     * @return void
     */
    public function show_get($id)
    {
        $category = $this->Ticket_category_model->getById($id);

        if (!$category) {
            $this->respondError('Kategori tidak ditemukan', 404);
            return;
        }

        $this->respond($category, 'Data berhasil diambil');
    }

    public function create_post()
    {
        $data = $this->getJsonInput();

        // ── Validasi required fields ────────────────────────────────────
        $errors = [];
        $name = isset($data['name']) ? $data['name'] : (isset($data['description']) ? $data['description'] : null);
        $departement = isset($data['department']) ? $data['department'] : (isset($data['departement']) ? $data['departement'] : null);
        
        if (empty($name)) {
            $errors[] = 'name (atau description) wajib diisi';
        }
        if (empty($departement)) {
            $errors[] = 'department wajib diisi';
        }

        if (!empty($errors)) {
            $this->respondError('Validasi gagal', 422, $errors);
            return;
        }

        $statusStr = isset($data['status']) ? $data['status'] : 'active';
        $statusInt = (strtolower($statusStr) === 'active' || $statusStr == 1) ? 1 : 0;

        $insertData = [
            'id'          => 'C' . date('ymd') . rand(1000, 9999),
            'name'        => $name,
            'departement' => $departement,
            'status'      => $statusInt
        ];

        $this->Ticket_category_model->insert($insertData);

        $this->respond(['id' => $insertData['id']], 'Kategori berhasil ditambahkan', true, 201);
    }

    public function update_put($id)
    {
        $category = $this->Ticket_category_model->getById($id);

        if (!$category) {
            $this->respondError('Kategori tidak ditemukan', 404);
            return;
        }

        $data = $this->getJsonInput();

        $updateData = [];
        if (isset($data['name']) || isset($data['description'])) {
            $updateData['name'] = isset($data['name']) ? $data['name'] : $data['description'];
        }
        if (isset($data['department']) || isset($data['departement'])) {
            $updateData['departement'] = isset($data['department']) ? $data['department'] : $data['departement'];
        }
        if (isset($data['status'])) {
            $statusStr = $data['status'];
            $updateData['status'] = (strtolower($statusStr) === 'active' || $statusStr == 1) ? 1 : 0;
        }

        if (empty($updateData)) {
            $this->respondError('Tidak ada data untuk diupdate', 422);
            return;
        }

        $this->Ticket_category_model->update($id, $updateData);

        $this->respond(null, 'Kategori berhasil diperbarui');
    }

    /**
     * DELETE /api/ticket-category/{id}
     *
     * @param  int  $id Category ID
     * @return void
     */
    public function delete_delete($id)
    {
        $category = $this->Ticket_category_model->getById($id);

        if (!$category) {
            $this->respondError('Kategori tidak ditemukan', 404);
            return;
        }

        $this->Ticket_category_model->softDelete($id);

        $this->respond(null, 'Kategori berhasil dihapus');
    }
}
