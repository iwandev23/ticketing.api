<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_department_model — Model untuk tabel `ticket_departement`.
 *
 * CATATAN: Tabel di DB bernama "ticket_departement" (typo bawaan, tidak diubah).
 * Hanya View — tidak ada insert/update/delete.
 */
class Ticket_department_model extends CI_Model
{
    /** @var string Nama tabel (typo bawaan dari DB) */
    protected $table = 'ticket_departement';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ambil semua departemen.
     *
     * @return array Array of department rows
     */
    public function getAll()
    {
        return $this->db
            ->get($this->table)
            ->result_array();
    }

    /**
     * Ambil single departemen berdasarkan ID.
     *
     * @param  int        $id Department ID
     * @return array|null Single row atau null
     */
    public function getById($id)
    {
        return $this->db
            ->where('departement_id', $id)
            ->get($this->table)
            ->row_array();
    }
}
