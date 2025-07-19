DO $$
DECLARE
    v_start DATE := '2025-01-01';
    v_end   DATE := '2028-01-01';
    v_cur   DATE := v_start;
    v_next  DATE;
    v_name  TEXT;
BEGIN
    WHILE v_cur < v_end LOOP
        v_next := (v_cur + INTERVAL '1 month')::DATE;
        v_name := format('appointments_%s', to_char(v_cur, 'YYYY_MM'));
        EXECUTE format(
            'CREATE TABLE IF NOT EXISTS %I PARTITION OF appointments FOR VALUES FROM (%L) TO (%L);',
            v_name, v_cur, v_next
        );
        v_cur := v_next;
    END LOOP;
END
$$;
