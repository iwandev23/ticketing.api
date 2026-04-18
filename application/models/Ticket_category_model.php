<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_category_model — Model untuk tabel `ticket_category`.
 *
 * Kolom: id, date, department, description, priority, status,
 *        category_id, location_id, location_name, created_at, created_by,
 *        created_byname, deleted_at
 */
class Ticket_category_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_category';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ambil semua kategori dengan pagination dan filter opsional.
     *
     * @param  array $filters Filter opsional
     * @param  int   $page    Halaman (1-based)
     * @param  int   $perPage Jumlah per halaman
     * @return array ['data' => [], 'total' => 0]
     */
    public function getAll($filters = [], $page = 1, $perPage = 10)
    {
        if (!empty($filters['department'])) {
            $this->db->where('departement', $filters['department']);
        }

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
     * Ambil single kategori berdasarkan ID.
     *
     * @param  int        $id Category ID
     * @return array|null Single row atau null
     */
    public function getById($id)
    {
        return $this->db
            ->where('id', $id)
            ->get($this->table)
            ->row_array();
    }

    /**
     * Insert kategori baru.
     *
     * @param  array $data Data kategori
     * @return int   Insert ID
     */
    public function insert($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update kategori.
     *
     * @param  int   $id   Category ID
     * @param  array $data Data update
     * @return bool  True jika berhasil
     */
    public function update($id, $data)
    {
        return $this->db
            ->where('id', $id)
            ->update($this->table, $data);
    }

    /**
     * Soft delete kategori.
     *
     * @param  int  $id Category ID
     * @return bool True jika berhasil
     */
    public function softDelete($id)
    {
        return $this->db
            ->where('id', $id)
            ->delete($this->table);
    }
}
