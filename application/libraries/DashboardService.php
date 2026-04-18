<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * DashboardService — Business logic untuk dashboard overview.
 *
 * Mengorkestrasi 6 query dari Dashboard_model dan menyusun
 * response JSON yang siap dikonsumsi frontend.
 *
 * Strategi performa:
 * - 6 query SQL ringan (aggregation, bukan fetch all)
 * - 0 loop PHP untuk aggregasi
 * - Semua JOIN dilakukan di SQL level
 */
class DashboardService
{
    /** @var CI_Controller */
    protected $CI;

    /** @var array Label bulan dalam Bahasa Indonesia */
    protected $monthLabels = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Dashboard_model');
    }

    /**
     * Ambil semua data dashboard dalam 1 call.
     *
     * @param  int   $year  Tahun (default: tahun sekarang)
     * @param  int   $month Bulan (default: bulan sekarang)
     * @return array Structured dashboard data
     */
    public function getOverview($year = null, $month = null)
    {
        // Default ke bulan & tahun sekarang
        if (!$year)  $year  = (int) date('Y');
        if (!$month) $month = (int) date('m');

        // Validasi range
        $month = max(1, min(12, (int) $month));
        $year  = max(2000, min(2099, (int) $year));

        $model = $this->CI->Dashboard_model;

        // ── 1. Summary Cards ─────────────────────────────────────────────
        $summary = $model->getSummaryCards($year, $month);

        // ── 2. Perbandingan bulan lalu ───────────────────────────────────
        $prevMonthTotal = $model->getPrevMonthTotal($year, $month);
        $percentChange  = 0;
        if ($prevMonthTotal > 0) {
            $percentChange = round(
                (($summary['total'] - $prevMonthTotal) / $prevMonthTotal) * 100,
                2
            );
        }

        // ── 3. Tiket per Department ──────────────────────────────────────
        $byDepartment = $model->getTicketsByDepartment($year, $month);
        $departmentData = array_map(function ($row) {
            return [
                'department' => $row['department_name'],
                'count'      => (int) $row['ticket_count'],
            ];
        }, $byDepartment);

        // ── 4. Distribusi Status ─────────────────────────────────────────
        $statusDist = $model->getStatusDistribution($year, $month);
        $statusData = array_map(function ($row) {
            return [
                'status_id' => (int) $row['status_id'],
                'label'     => $row['label'],
                'count'     => (int) $row['ticket_count'],
            ];
        }, $statusDist);

        // ── 5. Top Feedback ──────────────────────────────────────────────
        $topFeedback = $model->getTopFeedback($year, $month);
        $feedbackData = array_map(function ($row) {
            return [
                'description' => $row['action'],
                'count'       => (int) $row['feedback_count'],
            ];
        }, $topFeedback);

        // ── 6. Urgent Tickets ────────────────────────────────────────────
        $urgentTickets = $model->getUrgentOpenTickets();
        $urgentData = array_map(function ($row) {
            return [
                'id'             => $row['id'],
                'created_byname' => $row['created_byname'],
                'department'     => $row['department_name'],
                'priority_label' => $row['priority_label'],
                'status_label'   => $row['status_label'],
                'age_days'       => (int) $row['age_days'],
            ];
        }, $urgentTickets);

        // ── Build response ───────────────────────────────────────────────
        $monthLabel = isset($this->monthLabels[$month])
            ? $this->monthLabels[$month]
            : $month;

        return [
            'period' => [
                'month' => $month,
                'year'  => $year,
                'label' => $monthLabel . ' ' . $year,
            ],
            'summary_cards' => [
                'total_ticket'     => $summary['total'],
                'open'             => $summary['open'],
                'inprogress'       => $summary['inprogress'],
                'done'             => $summary['done'],
                'cancel'           => $summary['cancel'],
                'prev_month_total' => $prevMonthTotal,
                'percent_change'   => $percentChange,
            ],
            'tickets_by_department' => $departmentData,
            'status_distribution'   => $statusData,
            'top_feedback'          => $feedbackData,
            'urgent_tickets'        => $urgentData,
        ];
    }
}
