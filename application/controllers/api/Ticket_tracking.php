<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_tracking API Controller — CRUD master tracking steps.
 *
 * Endpoints:
 * - GET    /api/ticket-tracking        → index_get()
 * - GET    /api/ticket-tracking/{id}   → show_get($id)
 * - POST   /api/ticket-tracking        → create_post()
 * - PUT    /api/ticket-tracking/{id}   → update_put($id)
 * - DELETE /api/ticket-tracking/{id}   → delete_delete($id)
 */
class Ticket_tracking extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_tracking_model');
    }

    /**
     * GET /api/ticket-tracking
     *
     * List semua tracking steps. ORDER BY id ASC.
     *
     * @return void
     */
    public function index_get()
    {
        $page    = $this->input->get('page') ?: 1;
        $perPage = $this->input->get('per_page') ?: 10;

        $result = $this->Ticket_tracking_model->getAll([], $page, $perPage);

        $this->respondPaginated($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/ticket-tracking/{id}
     *
     * @param  int  $id Tracking step ID
     * @return void
     */
    public function show_get($id)
    {
        $step = $this->Ticket_tracking_model->getById($id);

        if (!$step) {
            $this->respondError('Tracking step tidak ditemukan', 404);
            return;
        }

        $this->respond($step, 'Data berhasil diambil');
    }

    /**
     * POST /api/ticket-tracking
     *
     * Required: name
     * Optional: description, updateby
     *
     * @return void
     */
    public function create_post()
    {
        $data = $this->getJsonInput();

        if (empty($data['name'])) {
            $this->respondError('Validasi gagal', 422, ['name wajib diisi']);
            return;
        }

        $insertData = [
            'name'        => $data['name'],
            'description' => isset($data['description']) ? $data['description'] : null,
            'updateby'    => isset($data['updateby']) ? $data['updateby'] : null,
        ];

        $id = $this->Ticket_tracking_model->insert($insertData);

        $this->respond(['id' => $id], 'Tracking step berhasil ditambahkan', true, 201);
    }

    /**
     * PUT /api/ticket-tracking/{id}
     *
     * @param  int  $id Tracking step ID
     * @return void
     */
    public function update_put($id)
    {
        $step = $this->Ticket_tracking_model->getById($id);

        if (!$step) {
            $this->respondError('Tracking step tidak ditemukan', 404);
            return;
        }

        $data = $this->getJsonInput();

        $updateData = [];
        if (isset($data['name']))        $updateData['name']        = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['updateby']))    $updateData['updateby']    = $data['updateby'];

        if (empty($updateData)) {
            $this->respondError('Tidak ada data untuk diupdate', 422);
            return;
        }

        $this->Ticket_tracking_model->update($id, $updateData);

        $this->respond(null, 'Tracking step berhasil diperbarui');
    }

    /**
     * DELETE /api/ticket-tracking/{id}
     *
     * Soft delete: SET status = 0.
     *
     * @param  int  $id Tracking step ID
     * @return void
     */
    public function delete_delete($id)
    {
        $step = $this->Ticket_tracking_model->getById($id);

        if (!$step) {
            $this->respondError('Tracking step tidak ditemukan', 404);
            return;
        }

        $this->Ticket_tracking_model->softDelete($id);

        $this->respond(null, 'Tracking step berhasil dihapus');
    }
}
