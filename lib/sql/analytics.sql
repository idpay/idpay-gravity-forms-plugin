SELECT status, sum(payment_amount) revenue, count(id) transactions
FROM %s
    WHERE form_id=%s
    AND status='active'
    AND is_fulfilled=1
    AND payment_method='IDPay'
GROUP BY status