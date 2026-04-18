<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard API Controller.
 *
 * Endpoint:
 * - GET /api/dashboard?month=4&year=2026 → index_get()
 *
 * Mengembalikan semua data dashboard dalam 1 request:
 * summary cards, tickets by department, status distribution,
 * top feedback, dan urgent tickets.
 */
class Dashboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('DashboardService');
    }

    /**
     * GET /api/dashboard
     *
     * Query params:
     * - month (int, 1-12, default: bulan sekarang)
     * - year  (int, default: tahun sekarang)
     *
     * @return void
     */
    public function index_get()
    {
        $month = $this->input->get('month') ?: (int) date('m');
        $year  = $this->input->get('year')  ?: (int) date('Y');

        $data = $this->dashboardservice->getOverview($year, $month);

        $this->respond($data, 'Data dashboard berhasil diambil');
    }
}
