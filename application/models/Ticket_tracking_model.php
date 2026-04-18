<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_tracking_model — Model untuk tabel `ticket_tracking`.
 *
 * Master data step-step timeline aktivitas.
 * Kolom: id (PK, auto_increment), name, description, updatedate, updateby, status (int, 1=active)
 */
class Ticket_tracking_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_tracking';

    public function __construct()
    {
        parent::__construct();
    }

    // ====================================================================
    // Read methods
    // ====================================================================

    /**
     * Ambil semua tracking steps dengan pagination.
     * ORDER BY id ASC — wajib untuk timeline progression.
     *
     * @param  array $filters Filter opsional (belum dipakai, reserved)
     * @param  int   $page    Halaman (1-based)
     * @param  int   $perPage Jumlah per halaman
     * @return array ['data' => [], 'total' => int]
     */
    public function getAll($filters = [], $page = 1, $perPage = 10)
    {
        $this->db->where('status', 1);
        $total = $this->db->count_all_results($this->table, false);

        $offset = ($page - 1) * $perPage;
        $this->db->order_by('id', 'ASC');
        $this->db->limit($perPage, $offset);
        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Ambil single tracking step berdasarkan ID.
     *
     * @param  int        $id Tracking step ID
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
     * Ambil semua step aktif tanpa pagination.
     * Digunakan oleh TimelineService untuk build timeline.
     *
     * @return array Array of active steps, ORDER BY id ASC
     */
    public function getActiveSteps()
    {
        return $this->db
            ->where('status', 1)
            ->order_by('id', 'ASC')
            ->get($this->table)
            ->result_array();
    }

    // ====================================================================
    // Write methods
    // ====================================================================

    /**
     * Insert tracking step baru.
     *
     * @param  array $data Data step
     * @return int   Insert ID
     */
    public function insert($data)
    {
        if (!isset($data['updatedate'])) {
            $data['updatedate'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /**
     * Update tracking step.
     *
     * @param  int   $id   Step ID
     * @param  array $data Data update
     * @return bool  True jika berhasil
     */
    public function update($id, $data)
    {
        $data['updatedate'] = date('Y-m-d H:i:s');

        return $this->db
            ->where('id', $id)
            ->update($this->table, $data);
    }

    /**
     * Soft delete: SET status = 0.
     *
     * @param  int  $id Step ID
     * @return bool True jika berhasil
     */
    public function softDelete($id)
    {
        return $this->db
            ->where('id', $id)
            ->update($this->table, [
                'status'     => 0,
                'updatedate' => date('Y-m-d H:i:s'),
            ]);
    }
}
