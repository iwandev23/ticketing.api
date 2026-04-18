-- Composite index: filter bulan + grouping department
ALTER TABLE ticket ADD INDEX idx_date_dept_status (date, department, status);

-- Index untuk feedback aggregation
ALTER TABLE ticket_feedback ADD INDEX idx_feedback_desc (description(50));

-- Index untuk urgent ticket filter
ALTER TABLE ticket ADD INDEX idx_priority_status (priority, status);
