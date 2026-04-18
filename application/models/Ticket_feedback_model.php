<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_feedback_model — Model untuk tabel `ticket_feedback`.
 *
 * Kolom: id, date, department, analysis, action, results, priority, status,
 *        category_id, location_id, location_name, created_at, created_by,
 *        created_byname, deleted_at, ticket_id
 */
class Ticket_feedback_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_feedback';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ambil semua feedback dengan pagination dan filter opsional.
     *
     * @param  array $filters Filter opsional
     * @param  int   $page    Halaman (1-based)
     * @param  int   $perPage Jumlah per halaman
     * @return array ['data' => [], 'total' => 0]
     */
    public function getAll($filters = [], $page = 1, $perPage = 10)
    {
        if (!empty($filters['ticket_id'])) {
            $this->db->where('ticket_id', $filters['ticket_id']);
        }

        $total = $this->db->count_all_results($this->table, false);

        $offset = ($page - 1) * $perPage;
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($perPage, $offset);
        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Ambil single feedback berdasarkan ID.
     *
     * @param  int        $id Feedback ID
     * @return array|null Single row atau null
     */
    public function getById($id)
    {
        return $this->db
            ->where('ticket_id', $id)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Ambil feedback terbaru berdasarkan ticket_id.
     *
     * @param  int        $ticketId Ticket ID
     * @return array|null Single row feedback terbaru atau null
     */
    public function getLatestByTicketId($ticketId)
    {
        return $this->db
            ->where('ticket_id', $ticketId)
            ->order_by('created_at', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Insert feedback baru.
     *
     * @param  array $data Data feedback
     * @return int   Insert ID
     */
    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update feedback.
     *
     * @param  int   $id   Feedback ID
     * @param  array $data Data update
     * @return bool  True jika berhasil
     */
    public function update($id, $data)
    {
        return false; // Table doesn't have ID
    }

    /**
     * Soft delete feedback.
     *
     * @param  int  $id Feedback ID
     * @return bool True jika berhasil
     */
    public function softDelete($id)
    {
        return false; // Table doesn't have deleted_at or ID
    }
}
