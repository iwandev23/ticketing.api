<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Base controller untuk semua API endpoint.
 *
 * Fitur:
 * - Validasi Bearer Token dari header Authorization
 * - Set CORS headers
 * - Handle OPTIONS preflight request
 * - Standar response helpers (respond, respondPaginated, respondError)
 * - Auto Content-Type: application/json
 */
class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // ── CORS Headers ────────────────────────────────────────────────
        $this->_setCorsHeaders();

        // ── Handle OPTIONS (preflight) ──────────────────────────────────
        if ($this->input->method(TRUE) === 'OPTIONS') {
            $this->output->set_status_header(200);
            exit;
        }

        // ── Auto JSON content type ──────────────────────────────────────
        $this->output->set_content_type('application/json');

        // ── Validasi Bearer Token ───────────────────────────────────────
        $this->_validateToken();
    }

    // ====================================================================
    // CORS
    // ====================================================================

    /**
     * Set header CORS pada setiap response.
     *
     * @return void
     */
    private function _setCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 3600');
    }

    // ====================================================================
    // Token auth
    // ====================================================================

    /**
     * Validasi Bearer Token dari header Authorization.
     * Jika tidak valid → langsung kirim 401 dan exit.
     *
     * @return void
     */
    private function _validateToken()
    {
        // ── Primary: CI's get_request_header ─────────────────────────────
        $authHeader = $this->input->get_request_header('Authorization', TRUE);

        // ── Fallback 1: REDIRECT_HTTP_AUTHORIZATION (MAMP CGI/FastCGI) ──
        if (empty($authHeader) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // ── Fallback 2: HTTP_AUTHORIZATION from SetEnvIf / RewriteRule ───
        if (empty($authHeader) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        // ── Fallback 3: apache_request_headers() ─────────────────────────
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $authHeader = $headers['authorization'];
            }
        }

        if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $this->respondError('Unauthorized — token tidak ditemukan', 401);
            echo $this->output->get_output();
            exit;
        }

        $token = $matches[1];

        // ── Cek token ke database / config ──────────────────────────────
        // Saat ini menggunakan config item. Ganti dengan lookup DB jika perlu.
        $validToken = $this->config->item('api_token');

        if ($validToken && $token !== $validToken) {
            $this->respondError('Unauthorized — token tidak valid', 401);
            echo $this->output->get_output();
            exit;
        }
    }

    // ====================================================================
    // OPTIONS handler (dipanggil dari route)
    // ====================================================================

    /**
     * Method untuk menangani OPTIONS preflight request yang diarahkan route.
     *
     * @return void
     */
    public function options()
    {
        $this->output->set_status_header(200);
    }

    // ====================================================================
    // Response helpers
    // ====================================================================

    /**
     * Kirim response JSON standar.
     *
     * @param  mixed  $data    Data payload
     * @param  string $message Pesan deskriptif
     * @param  bool   $status  Status sukses/gagal
     * @param  int    $code    HTTP status code
     * @return void
     */
    protected function respond($data, $message = 'Success', $status = true, $code = 200)
    {
        $response = [
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ];

        $this->output
            ->set_status_header($code)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Kirim response JSON dengan meta pagination.
     *
     * @param  array $data    Array data hasil query
     * @param  int   $total   Total seluruh record (sebelum pagination)
     * @param  int   $page    Halaman saat ini
     * @param  int   $perPage Jumlah item per halaman
     * @return void
     */
    protected function respondPaginated($data, $total, $page, $perPage)
    {
        $response = [
            'status'  => true,
            'message' => 'Data berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'total'        => (int) $total,
            ],
        ];

        $this->output
            ->set_status_header(200)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Kirim response error JSON.
     *
     * @param  string $message Pesan error
     * @param  int    $code    HTTP status code
     * @param  array  $errors  Detail error tambahan (opsional)
     * @return void
     */
    protected function respondError($message, $code = 400, $errors = [])
    {
        $response = [
            'status'  => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_status_header($code)
            ->set_output(json_encode($response, JSON_UNESCAPED_UNICODE));
    }

    // ====================================================================
    // Input helpers
    // ====================================================================

    /**
     * Ambil JSON body dari request POST/PUT.
     *
     * @return array
     */
    protected function getJsonInput()
    {
        $raw = $this->input->raw_input_stream;
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
