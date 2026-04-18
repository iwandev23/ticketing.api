<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

/*
| -------------------------------------------------------------------------
| REST API ROUTES — Ticketing System
| -------------------------------------------------------------------------
| Format: /api/{resource}
| Controller ada di subfolder api/
*/

// ── Dashboard ───────────────────────────────────────────────────────────
$route['api/dashboard']['get']      = 'api/dashboard/index_get';
$route['api/dashboard']['options']  = 'api/dashboard/options';

// ── Ticket (View Only) ──────────────────────────────────────────────────
$route['api/ticket']['get']              = 'api/ticket/index_get';
$route['api/ticket/(:any)']['get']       = 'api/ticket/show_get/$1';
$route['api/ticket']['options']          = 'api/ticket/options';
$route['api/ticket/(:any)']['options']   = 'api/ticket/options';

// ── Ticket Category (CRUD) ─────────────────────────────────────────────
$route['api/ticket-category']['get']             = 'api/ticket_category/index_get';
$route['api/ticket-category/(:any)']['get']      = 'api/ticket_category/show_get/$1';
$route['api/ticket-category']['post']            = 'api/ticket_category/create_post';
$route['api/ticket-category/(:any)']['put']      = 'api/ticket_category/update_put/$1';
$route['api/ticket-category/(:any)']['delete']   = 'api/ticket_category/delete_delete/$1';
$route['api/ticket-category']['options']         = 'api/ticket_category/options';
$route['api/ticket-category/(:any)']['options']  = 'api/ticket_category/options';

// ── Ticket Department (View Only) ───────────────────────────────────────
$route['api/ticket-department']['get']           = 'api/ticket_department/index_get';
$route['api/ticket-department/(:any)']['get']    = 'api/ticket_department/show_get/$1';
$route['api/ticket-department']['options']       = 'api/ticket_department/options';
$route['api/ticket-department/(:any)']['options']= 'api/ticket_department/options';

// ── Ticket Feedback (CRUD + Business Logic) ─────────────────────────────
$route['api/ticket-feedback']['get']             = 'api/ticket_feedback/index_get';
$route['api/ticket-feedback/(:any)']['get']      = 'api/ticket_feedback/show_get/$1';
$route['api/ticket-feedback']['post']            = 'api/ticket_feedback/create_post';
$route['api/ticket-feedback/(:any)']['put']      = 'api/ticket_feedback/update_put/$1';
$route['api/ticket-feedback/(:any)']['delete']   = 'api/ticket_feedback/delete_delete/$1';
$route['api/ticket-feedback']['options']         = 'api/ticket_feedback/options';
$route['api/ticket-feedback/(:any)']['options']  = 'api/ticket_feedback/options';

// ── Ticket Priority (CRUD) ─────────────────────────────────────────────
$route['api/ticket-priority']['get']             = 'api/ticket_priority/index_get';
$route['api/ticket-priority/(:any)']['get']      = 'api/ticket_priority/show_get/$1';
$route['api/ticket-priority']['post']            = 'api/ticket_priority/create_post';
$route['api/ticket-priority/(:any)']['put']      = 'api/ticket_priority/update_put/$1';
$route['api/ticket-priority/(:any)']['delete']   = 'api/ticket_priority/delete_delete/$1';
$route['api/ticket-priority']['options']         = 'api/ticket_priority/options';
$route['api/ticket-priority/(:any)']['options']  = 'api/ticket_priority/options';

// ── Ticket Status (CRUD) ───────────────────────────────────────────────
$route['api/ticket-status']['get']               = 'api/ticket_status/index_get';
$route['api/ticket-status/(:any)']['get']        = 'api/ticket_status/show_get/$1';
$route['api/ticket-status']['post']              = 'api/ticket_status/create_post';
$route['api/ticket-status/(:any)']['put']        = 'api/ticket_status/update_put/$1';
$route['api/ticket-status/(:any)']['delete']     = 'api/ticket_status/delete_delete/$1';
$route['api/ticket-status']['options']           = 'api/ticket_status/options';
$route['api/ticket-status/(:any)']['options']    = 'api/ticket_status/options';

// ── Ticket Tracking (CRUD — Master timeline steps) ─────────────────────
$route['api/ticket-tracking']['get']              = 'api/ticket_tracking/index_get';
$route['api/ticket-tracking/(:any)']['get']       = 'api/ticket_tracking/show_get/$1';
$route['api/ticket-tracking']['post']             = 'api/ticket_tracking/create_post';
$route['api/ticket-tracking/(:any)']['put']       = 'api/ticket_tracking/update_put/$1';
$route['api/ticket-tracking/(:any)']['delete']    = 'api/ticket_tracking/delete_delete/$1';
$route['api/ticket-tracking']['options']          = 'api/ticket_tracking/options';
$route['api/ticket-tracking/(:any)']['options']   = 'api/ticket_tracking/options';

// ── Ticket History (Timeline + Finish) ─────────────────────────────────
// Specific routes BEFORE (:any) wildcard
$route['api/ticket-history/can-finish']['get']        = 'api/ticket_history/can_finish_get';
$route['api/ticket-history/can-finish']['options']    = 'api/ticket_history/options';
$route['api/ticket-history/finish']['post']           = 'api/ticket_history/finish_post';
$route['api/ticket-history/finish']['options']        = 'api/ticket_history/options';
$route['api/ticket-history']['get']                   = 'api/ticket_history/index_get';
$route['api/ticket-history']['post']                  = 'api/ticket_history/create_post';
$route['api/ticket-history']['options']               = 'api/ticket_history/options';
$route['api/ticket-history/(:any)']['options']        = 'api/ticket_history/options';

// ── Ticket Scheduler (Cron endpoints) ──────────────────────────────────
$route['api/ticket-scheduler/auto-finish']['post']    = 'api/ticket_scheduler/auto_finish_post';
$route['api/ticket-scheduler/auto-finish']['options'] = 'api/ticket_scheduler/options';
