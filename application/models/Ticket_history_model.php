<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ticket_history_model — Model untuk tabel `ticket_history`.
 *
 * Activity log per ticket. Setiap perubahan state di-log sebagai row baru.
 * Kolom: id (PK, auto_increment), ticket_id, date, tracking_id (FK → ticket_tracking.id),
 *        description, updatedate, updateby, status (int, default 1)
 */
class Ticket_history_model extends CI_Model
{
    /** @var string Nama tabel */
    protected $table = 'ticket_history';

    public function __construct()
    {
        parent::__construct();
    }

    // ====================================================================
    // Read methods
    // ====================================================================

    /**
     * Ambil semua history per ticket, JOIN ticket_tracking untuk label step.
     * ORDER BY ticket_history.date ASC (kronologis).
     *
     * @param  string $ticketId Ticket ID (varchar)
     * @return array  Array of history entries dengan tracking info
     */
    public function getByTicketId($ticketId)
    {
        return $this->db
            ->select('
                ticket_history.id,
                ticket_history.ticket_id,
                ticket_history.date,
                ticket_history.tracking_id,
                ticket_tracking.name AS tracking_name,
                ticket_tracking.description AS tracking_description,
                ticket_history.description,
                ticket_history.updatedate,
                ticket_history.updateby,
                ticket_history.status
            ')
            ->from($this->table)
            ->join('ticket_tracking', 'ticket_tracking.id = ticket_history.tracking_id', 'left')
            ->where('ticket_history.ticket_id', $ticketId)
            ->where('ticket_history.status', 1)
            ->order_by('ticket_history.date', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Ambil history entry terbaru per ticket.
     *
     * @param  string     $ticketId Ticket ID
     * @return array|null Single row terbaru atau null
     */
    public function getLatestByTicketId($ticketId)
    {
        return $this->db
            ->select('
                ticket_history.*,
                ticket_tracking.name AS tracking_name
            ')
            ->from($this->table)
            ->join('ticket_tracking', 'ticket_tracking.id = ticket_history.tracking_id', 'left')
            ->where('ticket_history.ticket_id', $ticketId)
            ->where('ticket_history.status', 1)
            ->order_by('ticket_history.date', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
    }

    /**
     * Cek apakah tracking step tertentu sudah pernah terjadi untuk ticket.
     *
     * @param  string $ticketId   Ticket ID
     * @param  int    $trackingId Tracking step ID
     * @return bool   True jika step sudah ada di history
     */
    public function hasTrackingStep($ticketId, $trackingId)
    {
        $count = $this->db
            ->where('ticket_id', $ticketId)
            ->where('tracking_id', $trackingId)
            ->where('status', 1)
            ->count_all_results($this->table);

        return $count > 0;
    }

    // ====================================================================
    // Write methods
    // ====================================================================

    /**
     * Insert history entry baru (log event).
     *
     * @param  array $data Data history entry
     * @return int   Insert ID
     */
    public function insert($data)
    {
        if (!isset($data['date'])) {
            $data['date'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updatedate'])) {
            $data['updatedate'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['status'])) {
            $data['status'] = 1;
        }

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
}
