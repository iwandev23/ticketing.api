<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_status_model — Model untuk tabel `ticket_status`.
 *
 * KHUSUS: getAll() wajib ORDER BY id ASC (untuk step bar FE).
 *
 * Kolom: id, date, department, description, priority, status,
 *        category_id, location_id, location_name, created_at, created_by,
 *        created_byname, deleted_at
 */
class Ticket_status_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_status';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ambil semua status dengan pagination dan filter opsional.
     * ORDER BY id ASC — wajib untuk step bar FE.
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
        $this->db->order_by('status_id', 'ASC');
        $this->db->limit($perPage, $offset);
        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Ambil single status berdasarkan ID.
     *
     * @param  int        $id Status ID
     * @return array|null Single row atau null
     */
    public function getById($id)
    {
        return $this->db
            ->where('status_id', $id)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Insert status baru.
     *
     * @param  array $data Data status
     * @return int   Insert ID
     */
    public function insert($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update status.
     *
     * @param  int   $id   Status ID
     * @param  array $data Data update
     * @return bool  True jika berhasil
     */
    public function update($id, $data)
    {
        return $this->db
            ->where('status_id', $id)
            ->update($this->table, $data);
    }

    /**
     * Soft delete status.
     *
     * @param  int  $id Status ID
     * @return bool True jika berhasil
     */
    public function softDelete($id)
    {
        return $this->db
            ->where('status_id', $id)
            ->delete($this->table);
    }
}
