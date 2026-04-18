<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_priority_model — Model untuk tabel `ticket_priority`.
 *
 * Kolom: id, date, department, description, priority, status,
 *        category_id, location_id, location_name, created_at, created_by,
 *        created_byname, deleted_at
 */
class Ticket_priority_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_priority';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ambil semua priority dengan pagination dan filter opsional.
     *
     * @param  array $filters Filter opsional
     * @param  int   $page    Halaman (1-based)
     * @param  int   $perPage Jumlah per halaman
     * @return array ['data' => [], 'total' => 0]
     */
    public function getAll($filters = [], $page = 1, $perPage = 10)
    {
        $total = $this->db->count_all_results($this->table, false);

        $offset = ($page - 1) * $perPage;
        $this->db->limit($perPage, $offset);
        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Ambil single priority berdasarkan ID.
     *
     * @param  int        $id Priority ID
     * @return array|null Single row atau null
     */
    public function getById($id)
    {
        return $this->db
            ->where('priority_id', $id)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Insert priority baru.
     *
     * @param  array $data Data priority
     * @return int   Insert ID
     */
    public function insert($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update priority.
     *
     * @param  int   $id   Priority ID
     * @param  array $data Data update
     * @return bool  True jika berhasil
     */
    public function update($id, $data)
    {
        return $this->db
            ->where('priority_id', $id)
            ->update($this->table, $data);
    }

    /**
     * Soft delete priority.
     *
     * @param  int  $id Priority ID
     * @return bool True jika berhasil
     */
    public function softDelete($id)
    {
        return $this->db
            ->where('priority_id', $id)
            ->delete($this->table);
    }
}
