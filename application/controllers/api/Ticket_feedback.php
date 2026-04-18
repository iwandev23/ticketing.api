<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_feedback API Controller — CRUD + Business Logic.
 *
 * Endpoints:
 * - GET    /api/ticket-feedback        → index_get()        — List feedback
 * - GET    /api/ticket-feedback/{id}   → show_get($id)      — Detail
 * - POST   /api/ticket-feedback        → create_post()      — Submit multipart/form-data via FeedbackService
 * - PUT    /api/ticket-feedback/{id}   → update_put($id)    — Update standard
 * - DELETE /api/ticket-feedback/{id}   → delete_delete($id) — Soft delete
 */
class Ticket_feedback extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ticket_feedback_model');
        $this->load->library('FeedbackService');
    }

    /**
     * GET /api/ticket-feedback
     *
     * @return void
     */
    public function index_get()
    {
        $filters = [
            'ticket_id'  => $this->input->get('ticket_id'),
            'department' => $this->input->get('department'),
        ];
        $page    = $this->input->get('page') ?: 1;
        $perPage = $this->input->get('per_page') ?: 10;

        $result = $this->Ticket_feedback_model->getAll($filters, $page, $perPage);

        $this->respondPaginated($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/ticket-feedback/{id}
     *
     * @param  int  $id Feedback ID
     * @return void
     */
    public function show_get($id)
    {
        $feedback = $this->Ticket_feedback_model->getById($id);

        if (!$feedback) {
            $this->respondError('Feedback tidak ditemukan', 404);
            return;
        }

        $this->respond($feedback, 'Data berhasil diambil');
    }

    /**
     * POST /api/ticket-feedback — Submit feedback via FeedbackService.
     *
     * Content-Type: multipart/form-data
     * Supports optional files[] (multiple files).
     *
     * @return void
     */
    public function create_post()
    {
        try {
            // ── Ambil form fields ───────────────────────────────────────
            $data = $this->input->post();

            // ── Normalisasi $_FILES['files'] menjadi array of files ─────
            $files = [];

            if (isset($_FILES['files']) && !empty($_FILES['files']['name'])) {
                // Handle baik single file maupun multiple files[]
                if (is_array($_FILES['files']['name'])) {
                    // Multiple files: files[]
                    $fileCount = count($_FILES['files']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        // Skip slot yang kosong (tidak ada file dipilih)
                        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }

                        $files[] = [
                            'name'     => $_FILES['files']['name'][$i],
                            'type'     => $_FILES['files']['type'][$i],
                            'tmp_name' => $_FILES['files']['tmp_name'][$i],
                            'error'    => $_FILES['files']['error'][$i],
                            'size'     => $_FILES['files']['size'][$i],
                        ];
                    }
                } else {
                    // Single file: files (tanpa [])
                    if ($_FILES['files']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $files[] = [
                            'name'     => $_FILES['files']['name'],
                            'type'     => $_FILES['files']['type'],
                            'tmp_name' => $_FILES['files']['tmp_name'],
                            'error'    => $_FILES['files']['error'],
                            'size'     => $_FILES['files']['size'],
                        ];
                    }
                }
            }

            // ── Panggil FeedbackService ─────────────────────────────────
            $result = $this->feedbackservice->submit($data, $files);

            $this->respond($result, 'Feedback berhasil disimpan', true, 201);

        } catch (Exception $e) {
            $this->respondError('Gagal menyimpan feedback: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/ticket-feedback/{id}
     *
     * @param  int  $id Feedback ID
     * @return void
     */
    public function update_put($id)
    {
        $feedback = $this->Ticket_feedback_model->getById($id);

        if (!$feedback) {
            $this->respondError('Feedback tidak ditemukan', 404);
            return;
        }

        $data = $this->getJsonInput();

        $this->Ticket_feedback_model->update($id, $data);

        $this->respond(null, 'Feedback berhasil diperbarui');
    }

    /**
     * DELETE /api/ticket-feedback/{id}
     *
     * @param  int  $id Feedback ID
     * @return void
     */
    public function delete_delete($id)
    {
        $feedback = $this->Ticket_feedback_model->getById($id);

        if (!$feedback) {
            $this->respondError('Feedback tidak ditemukan', 404);
            return;
        }

        $this->Ticket_feedback_model->softDelete($id);

        $this->respond(null, 'Feedback berhasil dihapus');
    }
}
