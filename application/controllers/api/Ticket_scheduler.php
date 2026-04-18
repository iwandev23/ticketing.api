<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_scheduler API Controller — Scheduler/cron endpoints.
 *
 * Endpoints:
 * - POST /api/ticket-scheduler/auto-finish → auto_finish_post()
 *
 * Endpoint ini dipanggil oleh cron job secara berkala (rekomendasi: setiap 1 jam).
 * Contoh crontab:
 *   0 * * * * curl -X POST -H "Authorization: Bearer <token>" http://localhost/api/ticket-scheduler/auto-finish
 */
class Ticket_scheduler extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('TimelineService');
    }

    /**
     * POST /api/ticket-scheduler/auto-finish
     *
     * Auto-finish semua ticket yang eligible:
     * - Status = Feedback (3)
     * - Feedback sudah >= 24 jam
     * - Belum pernah di-finish
     *
     * @return void
     */
    public function auto_finish_post()
    {
        try {
            $result = $this->timelineservice->autoFinishEligibleTickets();

            $message = $result['finished_count'] > 0
                ? $result['finished_count'] . ' ticket berhasil di-auto-finish'
                : 'Tidak ada ticket yang perlu di-finish';

            $this->respond($result, $message);
        } catch (Exception $e) {
            $this->respondError('Scheduler error: ' . $e->getMessage(), 500);
        }
    }
}
