# PRD.md — API Contract Ticketing App (Akurat per Implementasi)

> Dokumen ini mencerminkan **implementasi yang sedang berjalan** di backend.
> Terakhir diperbarui: 2026-04-11

---

## Skema Database

### Tabel `ticket`
```
id              varchar(22)  PK
date            date         NULL
department      varchar(10)  NULL   — Departemen TUJUAN (dipilih user)
description     text         NULL
priority        int          NULL   — FK → ticket_priority.priority_id
status          int          NULL   — FK → ticket_status.status_id
category_id     varchar(15)  NULL   — FK → ticket_category.id
location_id     varchar(15)  NULL
location_name   varchar(20)  NULL
created_at      datetime     NULL
created_by      varchar(15)  NULL
created_byname  varchar(100) NULL
deleted_at      datetime     NULL   — Soft delete marker
```

### Tabel `ticket_feedback`
```
ticket_id       varchar(22)  PK     — FK → ticket.id (relasi 1:1)
analysis        varchar(255) NULL   — Analisis teknis
action          text         NULL   — Tindakan yang dilakukan (required saat submit)
results         varchar(255) NULL   — Hasil pekerjaan
file_url        varchar(100) NULL   — Legacy, tidak digunakan lagi
created_at      datetime     NULL
updated_at      datetime     NULL
deleted_at      datetime     NULL
created_by      varchar(15)  NULL
created_byname  varchar(100) NULL
```

### Tabel `ticket_category`
```
id              varchar(11)  PK
name            varchar(50)  NULL   — Nama kategori
status          int          NULL
departement     varchar(50)  NULL   — Typo bawaan DB, tidak diubah
created_at      datetime     NULL
```

### Tabel `ticket_status`
```
status_id       int          PK
status          varchar(15)  NULL   — Label status (Open, Done, dll)
```

### Tabel `ticket_priority`
```
priority_id     int          PK
priority_name   varchar(15)  NULL   — Label priority (Normal, Urgent)
```

### Tabel `ticket_departement`
```
departement_id  varchar(15)  PK
departement     varchar(15)  NULL   — Nama departemen
status          int          NULL
```
> **Catatan:** Nama tabel menggunakan typo "departement" bawaan, tidak diubah.

### Tabel `ticket_tracking`
```
id              int          PK, auto_increment
name            varchar(50)  NULL   — Label step (Dibuat, Diterima, dll)
description     varchar(200) NULL
updatedate      datetime     NULL
updateby        varchar(50)  NULL
status          int          NOT NULL  — 1=active, 0=inactive
```

### Tabel `ticket_history`
```
id              int          PK, auto_increment
ticket_id       varchar(22)  NULL   — FK → ticket.id
date            datetime     NULL
tracking_id     int          NULL   — FK → ticket_tracking.id
description     varchar(200) NULL   — Catatan event
updatedate      datetime     NULL
updateby        varchar(50)  NULL
status          int          NOT NULL, default 1
updated_byname  varchar(200) NULL
```

### Tabel `ticket_attachment`
```
id              varchar(22)  PK
ticket_id       varchar(22)  NOT NULL — FK → ticket.id
file_url        varchar(200) NULL
created_at      datetime     NULL
created_by      varchar(7)   NULL
created_byname  varchar(100) NULL
```

---

## Autentikasi

Semua endpoint membutuhkan **Bearer Token** di header `Authorization`.

```
Authorization: Bearer <api_token>
```

Token divalidasi oleh `MY_Controller._validateToken()` terhadap config item `api_token`.
Response jika tidak valid: `401 Unauthorized`.

---

## Daftar Endpoint

| Method            | Endpoint                                | Tipe       | Catatan                                                  |
|-------------------|-----------------------------------------|------------|----------------------------------------------------------|
| GET               | /api/dashboard                          | View       | Dashboard overview + statistik                           |
| GET               | /api/ticket                             | View       | List + LEFT JOIN feedback + filter + paginate             |
| GET               | /api/ticket/{id}                        | View       | Detail + timeline + supporting + attachments + feedback  |
| GET/POST/PUT/DELETE | /api/ticket-category                  | CRUD       | Master kategori                                          |
| GET               | /api/ticket-department                  | View       | Master departemen (read only)                            |
| GET/POST/PUT/DELETE | /api/ticket-feedback                  | CRUD       | Submit feedback → update status + kategori tiket         |
| GET/POST/PUT/DELETE | /api/ticket-priority                  | CRUD       | Master prioritas                                         |
| GET/POST/PUT/DELETE | /api/ticket-status                    | CRUD       | Master status. ORDER BY id ASC untuk step bar FE         |
| GET/POST/PUT/DELETE | /api/ticket-tracking                  | CRUD       | Master step timeline                                     |
| GET/POST          | /api/ticket-history                     | Read+Write | Timeline per ticket + manual log event                   |
| GET               | /api/ticket-history/can-finish          | View       | Cek eligibility finish (24 jam rule)                     |
| POST              | /api/ticket-history/finish              | Action     | Manual finish ticket                                     |
| POST              | /api/ticket-scheduler/auto-finish       | Cron       | Auto-finish tiket eligible                               |

---

## GET /api/dashboard

Dashboard overview. Semua data diambil via SQL aggregation (ringan).

**Query Parameters:**

| Parameter | Tipe | Required | Default        | Keterangan          |
|-----------|------|----------|----------------|---------------------|
| `month`   | int  | No       | bulan sekarang | Bulan filter (1-12) |
| `year`    | int  | No       | tahun sekarang | Tahun filter        |

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data dashboard berhasil diambil",
  "data": {
    "period": {
      "month": 4,
      "year": 2026,
      "label": "April 2026"
    },
    "summary_cards": {
      "total_ticket": 11,
      "open": 10,
      "inprogress": 0,
      "done": 1,
      "cancel": 0,
      "prev_month_total": 0,
      "percent_change": 0
    },
    "tickets_by_department": [
      { "department": "IT", "count": 7 },
      { "department": "HCD", "count": 3 }
    ],
    "status_distribution": [
      { "status_id": 1, "label": "Open", "count": 10 },
      { "status_id": 3, "label": "Done", "count": 1 }
    ],
    "top_feedback": [
      { "description": "Sudah dilakukan perbaikan", "count": 2 }
    ],
    "urgent_tickets": [
      {
        "id": "T202604090001",
        "created_byname": "HeadStore Simulasi",
        "department": "IT",
        "priority_label": "Urgent",
        "status_label": "Open",
        "age_days": 1
      }
    ]
  }
}
```

**Logika:**
- `summary_cards`: COUNT + SUM(CASE) dari tabel `ticket` per bulan
- `tickets_by_department`: GROUP BY department, LEFT JOIN `ticket_departement`
- `status_distribution`: GROUP BY status, LEFT JOIN `ticket_status`
- `top_feedback`: GROUP BY `ticket_feedback.action`, LEFT JOIN `ticket` untuk filter bulan. Key output tetap `description` agar kompatibel FE
- `urgent_tickets`: ticket dengan priority=2, status NOT IN (3,4), DATEDIFF untuk age_days

---

## GET /api/ticket (List)

**Query Parameters:**

| Parameter    | Tipe   | Required | Default | Keterangan                            |
|--------------|--------|----------|---------|---------------------------------------|
| `page`       | int    | No       | 1       | Nomor halaman                         |
| `per_page`   | int    | No       | 10      | Jumlah per halaman                    |
| `date_start` | string | No       | -       | Filter tanggal mulai (YYYY-MM-DD)     |
| `date_end`   | string | No       | -       | Filter tanggal akhir (YYYY-MM-DD)     |
| `department` | string | No       | -       | Filter by department ID               |
| `feedback`   | string | No       | all     | Filter: `all` / `ada` / `belum`       |

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": [
    {
      "id": "T202604030001",
      "date": "2026-04-03",
      "location_name": "Bunker Dipatiukur",
      "department": "P001-O0003",
      "priority": "1",
      "status": "1",
      "created_by": "usr_001",
      "created_byname": "Rifki Mohammad Idrus",
      "feedback_date": "2026-04-04 10:00:00",
      "feedback_result": "Sudah dilakukan perbaikan langsung"
    }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 7 }
}
```

**Logika JOIN:**
- `feedback_date` → `ticket_feedback.created_at` (terbaru per ticket via subquery MAX)
- `feedback_result` → `ticket_feedback.action` (terbaru per ticket, di-alias sebagai `feedback_result`)
- Jika belum ada feedback → kedua field = `null`

---

## GET /api/ticket/{id} (Detail)

**Path Parameters:**

| Parameter   | Tipe   | Required | Keterangan                     |
|-------------|--------|----------|--------------------------------|
| `ticket_id` | string | Yes      | ID tiket (contoh: T202604030001) |

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": {
    "id": "T202604030001",
    "date": "2026-04-03",
    "department": "P001-O0003",
    "description": "Tolong cek komputer kasir...",
    "priority": "1",
    "status": "1",
    "category_id": "HW",
    "category_name": "Hardware",
    "location_id": "10",
    "location_name": "Bunker Dipatiukur",
    "created_at": "2026-04-03 09:14:00",
    "created_by": "usr_001",
    "created_byname": "Ahmad Fauzi",
    "deleted_at": null,

    "timeline": [
      {
        "tracking_id": 1,
        "label": "Dibuat",
        "timestamp": "2026-04-03 09:20:21",
        "is_done": true,
        "description": "Ticket dibuat",
        "updated_byname": "Ahmad Fauzi"
      },
      {
        "tracking_id": 2,
        "label": "Diterima",
        "timestamp": null,
        "is_done": false,
        "description": null,
        "updated_byname": null
      },
      {
        "tracking_id": 3,
        "label": "Feedback",
        "timestamp": null,
        "is_done": false,
        "description": null,
        "updated_byname": null
      },
      {
        "tracking_id": 4,
        "label": "Finish",
        "timestamp": null,
        "is_done": false,
        "description": null,
        "updated_byname": null
      }
    ],

    "can_finish": false,
    "finish_reason": "Belum ada feedback",

    "supporting": {
      "name": "Budi Santoso",
      "department": "P001-O0003"
    },

    "attachments": [
      {
        "id": "1712345678901",
        "file_url": "uploads/attachments/2026/04/1712345678_abc123.jpg",
        "date": "2026-04-03 10:00:00",
        "created_byname": "Budi Santoso"
      }
    ],

    "feedback": {
      "id": "T202604030001",
      "analysis": "Kabel FO terputus",
      "action": "Sudah dilakukan perbaikan langsung",
      "results": "Koneksi kembali normal",
      "date": "2026-04-04 10:00:00"
    }
  }
}
```

### Logika Detail:

**category_name:**
- LEFT JOIN `ticket_category` ON `ticket_category.id = ticket.category_id`
- Jika `category_id` null → `category_name` = null

**timeline (data-driven via TimelineService):**
- Ambil semua step aktif dari `ticket_tracking` (status=1)
- Ambil history dari `ticket_history` untuk ticket ini (status=1)
- Merge: step yang ada di history → `is_done=true` + timestamp + description + updated_byname
- Step "Dibatalkan" (id=5) hanya muncul jika memang ada di history

**can_finish / finish_reason (via TimelineService::canFinish):**
- `false` jika ticket.status = 2 (sudah Done)
- `false` jika sudah pernah ada step Finish (tracking_id=4) di history
- `false` jika belum ada feedback
- `false` jika feedback.created_at belum >= 24 jam lalu
- `true` + reason "Eligible" jika semua kondisi terpenuhi

**supporting:**
- Ambil `created_byname` dari `ticket_feedback` terbaru → key `name`
- Key `department` → fallback dari `ticket.department` (departemen tujuan tiket)
- Jika belum ada feedback → `supporting = null`

**attachments:**
- Query `ticket_attachment` WHERE `ticket_id` = ticket ID
- Output keys: `id`, `file_url`, `date` (dari created_at), `created_byname`

**feedback:**
- Ambil dari `ticket_feedback` terbaru (ORDER BY created_at DESC LIMIT 1)
- Output keys: `id` (= ticket_id), `analysis`, `action`, `results`, `date` (dari created_at)
- Jika belum ada feedback → semua key = `null`

---

## POST /api/ticket-feedback (Create)

Gunakan `multipart/form-data` karena mendukung upload file.

**Request Body (Form-Data):**

| Field            | Tipe   | Required | Keterangan                                              |
|------------------|--------|----------|---------------------------------------------------------|
| `ticket_id`      | string | **Yes**  | ID tiket yang di-feedback                               |
| `department`     | string | **Yes**  | Departemen tujuan (validasi saja, tidak disimpan ke feedback) |
| `action`         | string | **Yes**  | Tindakan yang dilakukan                                 |
| `category_id`    | string | No       | Koreksi kategori tiket (update ke `ticket.category_id`) |
| `analysis`       | string | No       | Analisis teknis                                         |
| `results`        | string | No       | Hasil pekerjaan                                         |
| `created_by`     | string | No       | User ID pembuat                                         |
| `created_byname` | string | No       | Nama pembuat                                            |
| `files[]`        | file   | No       | Lampiran multi-file. Max 10MB/file. Format: jpg/jpeg/png/pdf |

### Business Logic (dalam SATU transaksi DB):

1. Validasi field wajib: `action`, `department`, `ticket_id`
2. Cek ticket exist (deleted_at IS NULL)
3. Cek apakah feedback sudah ada (relasi 1:1). Jika sudah → throw Exception
4. Validasi files[] jika ada: tipe (jpg/jpeg/png/pdf) + max 10MB per file
5. Start Transaksi (`$this->db->trans_start()`)
6. INSERT ke `ticket_feedback` (kolom: ticket_id, analysis, action, results, created_at, updated_at, created_by, created_byname)
7. Jika `category_id` dikirim → UPDATE `ticket.category_id` (opsional)
8. Jika ada files[]: loop tiap file → upload ke `uploads/attachments/{year}/{month}/` → INSERT ke `ticket_attachment` per file
9. Log event via `TimelineService::logEvent(ticket_id, 3, ...)` → auto-sync `ticket.status = 3` (Feedback)
10. Complete Transaksi (`$this->db->trans_complete()`)
11. Jika gagal → rollback semua

**Response Sukses (201 Created):**
```json
{
  "status": true,
  "message": "Feedback berhasil disimpan",
  "data": {
    "feedback": {
      "id": 0,
      "action": "Sudah dilakukan perbaikan langsung",
      "results": "Koneksi kembali normal",
      "date": "2026-04-07 10:00:00",
      "created_byname": "Budi Santoso"
    },
    "attachments": [
      {
        "id": "17123456781234",
        "file_name": "1712345678_abc123.jpg",
        "original_name": "foto_kerusakan.jpg",
        "file_path": "uploads/attachments/2026/04/1712345678_abc123.jpg"
      }
    ],
    "ticket_status_updated_to": "Feedback",
    "ticket_category_updated_to": "HW"
  }
}
```

> **Catatan:** `feedback.id` bernilai 0 karena PK tabel `ticket_feedback` adalah `ticket_id` (varchar), bukan auto_increment. `ticket_category_updated_to` = null jika `category_id` tidak dikirim.

**Response Error:**

| Code  | Keterangan                                                          |
|-------|---------------------------------------------------------------------|
| `500` | Validasi gagal / ticket tidak ditemukan / feedback sudah ada / transaksi gagal |

---

## PUT /api/ticket-feedback/{id}

> **Status:** Endpoint terdaftar di routes tapi **method `update()` di model mengembalikan `return false`** karena tabel `ticket_feedback` menggunakan `ticket_id` sebagai PK, bukan auto_increment ID.

---

## DELETE /api/ticket-feedback/{id}

> **Status:** Endpoint terdaftar di routes tapi **method `softDelete()` di model mengembalikan `return false`** karena tabel `ticket_feedback` menggunakan `ticket_id` sebagai PK.

---

## Timeline & Finish Flow

### Correlation Map (TimelineService)

| tracking_id | Step       | ticket.status yang di-sync |
|-------------|------------|----------------------------|
| 1           | Dibuat     | 1 (Open)                   |
| 2           | Diterima   | 1 (Open, tetap)            |
| 3           | Feedback   | 3 (Feedback)               |
| 4           | Finish     | 2 (Done)                   |
| 5           | Dibatalkan | 4 (Cancel)                 |

> Semua perubahan tracking **WAJIB** lewat `TimelineService::logEvent()`. Method ini akan auto-sync `ticket.status` sesuai tabel di atas.

### GET /api/ticket-tracking

Ambil master data step tracking (milestone). Digunakan FE untuk referensi.

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": [
    { "id": "1", "name": "Dibuat", "description": "Ticket open", "status": 1 },
    { "id": "2", "name": "Diterima", "description": "Sudah dilihat dept ahli", "status": 1 },
    { "id": "3", "name": "Feedback", "description": "Sudah difeedback", "status": 1 },
    { "id": "4", "name": "Finish", "description": "Disubmit finish oleh user", "status": 1 },
    { "id": "5", "name": "Dibatalkan", "description": "Tiket dibatalkan", "status": 1 }
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 5 }
}
```

### GET /api/ticket-history?ticket_id=X

Ambil riwayat tracking per ticket. JOIN `ticket_tracking` untuk label.

**Query Parameters:**

| Parameter   | Tipe   | Required | Keterangan |
|-------------|--------|----------|------------|
| `ticket_id` | string | **Yes**  | ID tiket   |

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": [
    {
      "id": "1",
      "ticket_id": "T202604030001",
      "date": "2026-04-03 09:20:21",
      "tracking_id": "1",
      "tracking_name": "Dibuat",
      "tracking_description": "Ticket open",
      "description": "Ticket dibuat",
      "updatedate": "2026-04-03 09:20:21",
      "updateby": "usr_001",
      "status": "1"
    }
  ]
}
```

### POST /api/ticket-history

Manual log event ke timeline via `TimelineService::logEvent()`.

**Request Body (JSON):**

| Field         | Tipe   | Required | Keterangan               |
|---------------|--------|----------|--------------------------|
| `ticket_id`   | string | **Yes**  | ID tiket                 |
| `tracking_id` | int    | **Yes**  | ID tracking step (1-5)   |
| `description` | string | No       | Catatan                  |
| `updateby`    | string | No       | Siapa yang melakukan     |

**Response Sukses (201 Created):**
```json
{ "status": true, "message": "Event berhasil dicatat", "data": { "id": 4 } }
```

### GET /api/ticket-history/can-finish?ticket_id=X

Cek apakah ticket eligible untuk di-finish. FE gunakan untuk show/hide tombol Finish.

**Business Rules (semua harus terpenuhi):**
1. `ticket.status` ≠ 2 (belum Done)
2. Belum pernah ada step Finish (tracking_id=4) di `ticket_history`
3. Sudah ada feedback di `ticket_feedback`
4. `feedback.created_at` sudah ≥ **24 jam** yang lalu

**Response (200 OK):**
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": {
    "can_finish": false,
    "reason": "Belum mencapai 24 jam sejak feedback terakhir (baru 5.2 jam)",
    "feedback_at": "2026-04-06 04:08:07",
    "hours_since_feedback": 5.2
  }
}
```

**Kemungkinan nilai `reason`:**

| reason                                  | Kondisi                            |
|-----------------------------------------|------------------------------------|
| `Ticket tidak ditemukan`                | ticket_id tidak valid / deleted    |
| `Ticket sudah selesai (Done)`           | status = 2                         |
| `Ticket sudah pernah di-finish`         | Ada step Finish di history         |
| `Belum ada feedback`                    | Belum ada data di ticket_feedback  |
| `Belum mencapai 24 jam...`              | Jeda waktu belum cukup             |
| `Eligible — feedback telah melebihi 1x24 jam` | ✅ Boleh finish              |

### POST /api/ticket-history/finish

Dipanggil ketika user menekan tombol Finish di UI.

**Request Body (JSON):**

| Field       | Tipe   | Required | Keterangan           |
|-------------|--------|----------|----------------------|
| `ticket_id` | string | **Yes**  | ID tiket             |
| `updateby`  | string | No       | Siapa yang memfinish |

**Response Sukses (200 OK):**
```json
{
  "status": true,
  "message": "Ticket berhasil di-finish",
  "data": {
    "ticket_id": "T20260408FIN01",
    "history_id": 10,
    "finished_at": "2026-04-08 11:00:00",
    "status_synced_to": 2
  }
}
```

**Response Error:**

| Code  | Keterangan                    |
|-------|-------------------------------|
| `422` | Tidak eligible (lihat reason) |

### POST /api/ticket-scheduler/auto-finish

Endpoint cron. Scan semua ticket eligible lalu auto-finish.

**Logic:**
1. Cari ticket dengan `status = 3` (Feedback)
2. `ticket_feedback.created_at` sudah >= 24 jam
3. Belum ada step Finish di `ticket_history`
4. Loop → `finishTicket()` per ticket → auto-sync status ke Done (2)

**Response (200 OK):**
```json
{
  "status": true,
  "message": "3 ticket berhasil di-auto-finish",
  "data": {
    "finished_count": 3,
    "ticket_ids": ["T20260408FIN01", "T20260408FIN02"],
    "errors": []
  }
}
```

---

## Master Data CRUD Endpoints

### Ticket Category — /api/ticket-category

**GET** list: pagination + filter by `department`.
**GET /{id}**: detail by ID.
**POST** create:

| Field         | Tipe   | Required | Keterangan     |
|---------------|--------|----------|----------------|
| `name`        | string | **Yes**  | Nama kategori (juga terima key `description`) |
| `department`  | string | **Yes**  | Department (juga terima key `departement`)    |
| `status`      | string | No       | "active" / "inactive" (default: active → 1)   |

> Controller menerima baik `name` maupun `description` untuk backward-compatibility.
> Data disimpan di kolom `name` dan `departement` pada tabel.

**PUT /{id}** update: field opsional, minimal satu harus ada.
**DELETE /{id}**: hard delete (bukan soft delete, tabel tidak punya `deleted_at`).

### Ticket Priority — /api/ticket-priority

CRUD standar. Kolom: `priority_id` (PK), `priority_name`.

### Ticket Status — /api/ticket-status

CRUD standar. Kolom: `status_id` (PK), `status`. ORDER BY `id ASC` untuk step bar FE.

### Ticket Department — /api/ticket-department

**Read-only** (hanya GET). Kolom: `departement_id` (PK), `departement`, `status`.

### Ticket Tracking — /api/ticket-tracking

CRUD standar. Soft delete via `status = 0`. ORDER BY `id ASC` untuk timeline.

---

## Aturan Umum

- **Soft delete (ticket):** `UPDATE SET deleted_at = NOW()` — TIDAK BOLEH DELETE FROM
- **Soft delete (tracking):** `UPDATE SET status = 0`
- **Filter list:** Semua query list ticket: `WHERE deleted_at IS NULL`
- **Warna badge:** Urusan FE, BE tidak return warna apapun
- **Transaksi:** Wajib jika menyentuh lebih dari 1 tabel
- **Status sync:** Semua perubahan status ticket WAJIB lewat `TimelineService::logEvent()`
- **Feedback relasi 1:1:** Satu ticket hanya boleh punya satu feedback. Submit kedua akan ditolak
- **Kategori opsional saat feedback:** Teknisi boleh mengoreksi kategori tiket via `category_id` saat submit feedback. Jika tidak dikirim, kategori tetap menggunakan nilai awal

---

## Response Format Standar

### Sukses (non-paginated):
```json
{
  "status": true,
  "message": "...",
  "data": { ... }
}
```

### Sukses (paginated):
```json
{
  "status": true,
  "message": "Data berhasil diambil",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 45
  }
}
```

### Error:
```json
{
  "status": false,
  "message": "Pesan error...",
  "errors": ["detail error 1", "detail error 2"]
}
```

> Key `errors` hanya muncul jika ada detail error tambahan.
