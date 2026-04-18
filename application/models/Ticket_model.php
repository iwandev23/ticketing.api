<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_model — Model untuk tabel `ticket`.
 *
 * Ticket hanya View (read-only), tidak ada insert/update/delete.
 * Kolom: id, ticket_number, date, department, description, priority, status,
 *        category_id, location_id, location_name, created_at, created_by,
 *        created_byname, deleted_at
 */
class Ticket_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket';

    public function __construct()
    {
        parent::__construct();
    }

    // ====================================================================
    // Read methods
    // ====================================================================

    /**
     * Ambil list ticket dengan filter dan pagination.
     *
     * JOIN ticket_feedback untuk ambil feedback_date dan feedback_result
     * (latest per ticket).
     *
     * Filter yang didukung:
     * - date_start : tanggal mulai (>=)
     * - date_end   : tanggal akhir (<=)
     * - department : filter department
     * - feedback   : all | ada | belum
     *
     * @param  array $filters Associative array filter
     * @param  int   $page    Halaman (1-based)
     * @param  int   $perPage Jumlah per halaman
     * @return array ['data' => [], 'total' => 0]
     */
    public function getList($filters = [], $page = 1, $perPage = 10)
    {
        // ── Subquery: feedback terbaru per ticket ───────────────────────
        $feedbackSub = '(SELECT tf1.ticket_id, tf1.created_at AS feedback_date, tf1.action AS feedback_result
            FROM ticket_feedback tf1
            INNER JOIN (
                SELECT ticket_id, MAX(created_at) AS max_created_at
                FROM ticket_feedback
                GROUP BY ticket_id
            ) tf2 ON tf1.ticket_id = tf2.ticket_id AND tf1.created_at = tf2.max_created_at
        ) AS latest_fb';

        // ── Query 1: Hitung total ───────────────────────────────────────
        $this->_applyFilters($feedbackSub, $filters);
        $total = $this->db->count_all_results();

        // ── Query 2: Ambil data dengan pagination ───────────────────────
        $this->db->select('
            ticket.id,
            ticket.date,
            ticket.location_name,
            ticket.department,
            ticket.priority,
            ticket.status,
            ticket.created_by,
            ticket.created_byname,
            latest_fb.feedback_date,
            latest_fb.feedback_result
        ');
        $this->_applyFilters($feedbackSub, $filters);

        $offset = ($page - 1) * $perPage;
        $this->db->order_by('ticket.id', 'DESC');
        $this->db->limit($perPage, $offset);

        $data = $this->db->get()->result_array();

        return [
            'data'  => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Terapkan filter dan JOIN ke query builder.
     * Digunakan internal untuk menghindari duplikasi kode.
     *
     * @param  string $feedbackSub Subquery string untuk LEFT JOIN
     * @param  array  $filters     Filter array
     * @return void
     */
    private function _applyFilters($feedbackSub, $filters)
    {
        $this->db->from($this->table);
        $this->db->join($feedbackSub, 'latest_fb.ticket_id = ticket.id', 'left');
        $this->db->where('ticket.deleted_at IS NULL');

        if (!empty($filters['date_start'])) {
            $this->db->where('ticket.date >=', $filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ticket.date <=', $filters['date_end']);
        }
        if (!empty($filters['department'])) {
            $this->db->where('ticket.department', $filters['department']);
        }
        if (!empty($filters['feedback']) && $filters['feedback'] !== 'all') {
            if ($filters['feedback'] === 'ada') {
                $this->db->where('latest_fb.feedback_date IS NOT NULL');
            } elseif ($filters['feedback'] === 'belum') {
                $this->db->where('latest_fb.feedback_date IS NULL');
            }
        }
    }

    /**
     * Ambil single ticket berdasarkan ID.
     *
     * @param  int        $id Ticket ID
     * @return array|null Single row atau null jika tidak ditemukan
     */
    public function getById($id)
    {
        $this->db->select('ticket.*, ticket_category.name AS category_name');
        $this->db->from($this->table);
        $this->db->join('ticket_category', 'ticket_category.id = ticket.category_id', 'left');
        $this->db->where('ticket.id', $id);
        $this->db->where('ticket.deleted_at IS NULL');
        
        return $this->db->get()->row_array();
    }
}
