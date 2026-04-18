<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard_model — Model khusus untuk query dashboard.
 *
 * Semua query menggunakan SQL aggregation (COUNT, SUM CASE, GROUP BY)
 * agar ringan dan tidak membebani server meskipun data besar.
 *
 * Tabel yang digunakan:
 * - ticket           (data tiket)
 * - ticket_feedback   (data feedback)
 * - ticket_status     (master status: 1=Open, 2=Inprogress, 3=Done, 4=Cancel)
 * - ticket_priority   (master priority: 1=Normal, 2=Urgent)
 * - ticket_departement (master department)
 */
class Dashboard_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // ====================================================================
    // 1. Summary Cards — Total, Open, Inprogress, Done per bulan
    // ====================================================================

    /**
     * Hitung total tiket dan breakdown per status untuk bulan tertentu.
     * Single query dengan SUM(CASE...) — 1 query, 1 result row.
     *
     * @param  int   $year  Tahun filter
     * @param  int   $month Bulan filter (1-12)
     * @return array ['total' => int, 'open' => int, 'inprogress' => int, 'done' => int, 'cancel' => int]
     */
    public function getSummaryCards($year, $month)
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inprogress,
                    SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS done,
                    SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS cancel
                FROM ticket
                WHERE YEAR(date) = ? AND MONTH(date) = ?
                  AND deleted_at IS NULL";

        $result = $this->db->query($sql, [$year, $month])->row_array();

        return [
            'total'      => (int) ($result['total'] ?? 0),
            'open'       => (int) ($result['open_count'] ?? 0),
            'inprogress' => (int) ($result['inprogress'] ?? 0),
            'done'       => (int) ($result['done'] ?? 0),
            'cancel'     => (int) ($result['cancel'] ?? 0),
        ];
    }

    // ====================================================================
    // 2. Month Comparison — Perbandingan total bulan ini vs bulan lalu
    // ====================================================================

    /**
     * Hitung total tiket bulan sebelumnya untuk perbandingan.
     * Single COUNT query.
     *
     * @param  int $year  Tahun
     * @param  int $month Bulan
     * @return int Total tiket bulan sebelumnya
     */
    public function getPrevMonthTotal($year, $month)
    {
        // Hitung bulan sebelumnya
        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear  = $year - 1;
        }

        $sql = "SELECT COUNT(*) AS total
                FROM ticket
                WHERE YEAR(date) = ? AND MONTH(date) = ?
                  AND deleted_at IS NULL";

        $result = $this->db->query($sql, [$prevYear, $prevMonth])->row_array();

        return (int) ($result['total'] ?? 0);
    }

    // ====================================================================
    // 3. Tickets by Department — Chart "Tiket per Dept Tujuan"
    // ====================================================================

    /**
     * Hitung jumlah tiket per department, JOIN ke master untuk nama.
     * GROUP BY + ORDER BY count DESC, LIMIT 5.
     *
     * @param  int   $year  Tahun
     * @param  int   $month Bulan
     * @param  int   $limit Max department yang ditampilkan
     * @return array [{ department_id, department_name, count }]
     */
    public function getTicketsByDepartment($year, $month, $limit = 5)
    {
        $sql = "SELECT
                    t.department AS department_id,
                    COALESCE(d.departement, t.department) AS department_name,
                    COUNT(*) AS ticket_count
                FROM ticket t
                LEFT JOIN ticket_departement d ON t.department = d.departement_id
                WHERE YEAR(t.date) = ? AND MONTH(t.date) = ?
                  AND t.deleted_at IS NULL
                GROUP BY t.department, d.departement
                ORDER BY ticket_count DESC
                LIMIT ?";

        return $this->db->query($sql, [$year, $month, $limit])->result_array();
    }

    // ====================================================================
    // 4. Status Distribution — Donut chart
    // ====================================================================

    /**
     * Hitung jumlah tiket per status, JOIN ke master status untuk label.
     * GROUP BY status.
     *
     * @param  int   $year  Tahun
     * @param  int   $month Bulan
     * @return array [{ status_id, label, count }]
     */
    public function getStatusDistribution($year, $month)
    {
        $sql = "SELECT
                    t.status AS status_id,
                    COALESCE(s.status, t.status) AS label,
                    COUNT(*) AS ticket_count
                FROM ticket t
                LEFT JOIN ticket_status s ON t.status = s.status_id
                WHERE YEAR(t.date) = ? AND MONTH(t.date) = ?
                  AND t.deleted_at IS NULL
                GROUP BY t.status, s.status
                ORDER BY ticket_count DESC";

        return $this->db->query($sql, [$year, $month])->result_array();
    }

    // ====================================================================
    // 5. Top Feedback — Bar chart "Result Feedback Terbanyak"
    // ====================================================================

    /**
     * Hitung feedback terbanyak berdasarkan action (tindakan perbaikan).
     * JOIN ke ticket untuk filter bulan.
     * GROUP BY action, LIMIT top N.
     *
     * @param  int   $year  Tahun
     * @param  int   $month Bulan
     * @param  int   $limit Max item
     * @return array [{ action, count }]
     */
    public function getTopFeedback($year, $month, $limit = 5)
    {
        $sql = "SELECT
                    f.action,
                    COUNT(*) AS feedback_count
                FROM ticket_feedback f
                INNER JOIN ticket t ON f.ticket_id = t.id
                WHERE YEAR(t.date) = ? AND MONTH(t.date) = ?
                  AND t.deleted_at IS NULL
                GROUP BY f.action
                ORDER BY feedback_count DESC
                LIMIT ?";

        return $this->db->query($sql, [$year, $month, $limit])->result_array();
    }

    // ====================================================================
    // 6. Urgent Open Tickets — Tabel "Tiket Urgent Belum Selesai"
    // ====================================================================

    /**
     * Ambil tiket urgent (priority=2) yang belum selesai (status NOT IN 3,4).
     * JOIN department + status + priority untuk label.
     * Hitung umur tiket (age_days) langsung di SQL.
     * TIDAK filter per bulan — menampilkan semua tiket urgent aktif saat ini.
     *
     * @param  int   $limit Max tiket
     * @return array [{ id, created_byname, department_name, priority_label, status_label, age_days }]
     */
    public function getUrgentOpenTickets($limit = 10)
    {
        $sql = "SELECT
                    t.id,
                    t.created_byname,
                    COALESCE(d.departement, t.department) AS department_name,
                    COALESCE(p.priority_name, 'Unknown') AS priority_label,
                    COALESCE(s.status, 'Unknown') AS status_label,
                    DATEDIFF(NOW(), t.created_at) AS age_days
                FROM ticket t
                LEFT JOIN ticket_departement d ON t.department = d.departement_id
                LEFT JOIN ticket_priority p ON t.priority = p.priority_id
                LEFT JOIN ticket_status s ON t.status = s.status_id
                WHERE t.priority = 2
                  AND t.status NOT IN (3, 4)
                  AND t.deleted_at IS NULL
                ORDER BY t.created_at ASC
                LIMIT ?";

        return $this->db->query($sql, [$limit])->result_array();
    }
}
