import http from 'k6/http';
import { check, sleep } from 'k6';

// إعدادات الـ K6 لاختبار الضغط (المتطلب 9)
export let options = {
    vus: 50, // 50 مستخدم متزامن
    duration: '10s', // لمدة 10 ثواني
};

export default function () {
    // ----------------------------------------------------------------
    // حل مشكلة الويندوز: التوزيع على 4 خوادم لمحاكاة التزامن الحقيقي
    // ----------------------------------------------------------------
    const PORTS = [8000, 8001, 8002, 8003];
    const port = PORTS[Math.floor(Math.random() * PORTS.length)];
    const BASE_URL = `http://127.0.0.1:${port}/api`;

    // ----------------------------------------------------------------
    // Req 1 & 7: Race Condition (Before / After)
    // ----------------------------------------------------------------
    let payload = JSON.stringify({ product_id: 1, amount: 1 });
    let params = { headers: { 'Content-Type': 'application/json' } };
    
    // (فك التعليق لاختبار الطريقة الخاطئة التي تؤدي لسالب في المخزون)
    // let resUnsafe = http.post(`${BASE_URL}/inventory/unsafe-decrease`, payload, params);
    
    let resSafe = http.post(`${BASE_URL}/inventory/safe-decrease`, payload, params);
    check(resSafe, { 'Safe Decrease hit': (r) => r.status === 200 || r.status === 400 });

    // (فك التعليق لاختبار الحل البديل باستخدام الـ Optimistic Lock)
    // let resOptimistic = http.post(`${BASE_URL}/inventory/optimistic-decrease`, payload, params);
    // check(resOptimistic, { 'Optimistic Decrease hit': (r) => r.status === 200 || r.status === 400 });

    // ----------------------------------------------------------------
    // Req 3: Async Queues (Before / After)
    // ----------------------------------------------------------------
    // Before: Email Sync (بطيء جداً ويوقف الخادم)
    // let resEmailSync = http.get(`${BASE_URL}/test/email-sync`);
    
    // After: Email Async (سريع جداً)
    let resEmailAsync = http.get(`${BASE_URL}/test/email-async`);
    check(resEmailAsync, { 'Async Email (Fast)': (r) => r.status === 200 });

    // ----------------------------------------------------------------
    // Req 4: Batch Processing (Before / After)
    // ----------------------------------------------------------------
    // Before: Load All in Memory (قد يسبب Crash)
    // let resBatchUnsafe = http.get(`${BASE_URL}/test/batch-unsafe`);

    // After: Safe Chunking
    let resBatchSafe = http.get(`${BASE_URL}/test/batch-safe`);
    check(resBatchSafe, { 'Batch Chunking (Safe)': (r) => r.status === 200 });

    // ----------------------------------------------------------------
    // Req 6: Caching (Before / After)
    // ----------------------------------------------------------------
    // Before: DB Query directly
    let resDb = http.get(`${BASE_URL}/test/products-db`);
    check(resDb, { 'Products DB fetched': (r) => r.status === 200 });

    // After: Redis Cache
    let resCache = http.get(`${BASE_URL}/test/products-cache`);
    check(resCache, { 'Products Cache hit': (r) => r.status === 200 });

    sleep(0.5); // استراحة لتجنب انهيار الجهاز المحلي أثناء التجربة
}
