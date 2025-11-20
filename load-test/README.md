# Load Testing Laravel Reverb - Live Cam

## Setup

```bash
cd load-test
npm install
```

## Menjalankan Test

### 1. HTTP API Test
Test endpoint HTTP (viewer join, chat, status):

```bash
# Jalankan full test
artillery run ../artillery.yaml

# Dengan output ke file
artillery run ../artillery.yaml --output results/http-test.json

# Generate HTML report
artillery report results/http-test.json --output results/http-report.html
```

### 2. WebSocket Test
Test koneksi WebSocket ke Reverb:

```bash
artillery run ../artillery-websocket.yaml --output results/ws-test.json
```

### 3. Quick Test (untuk development)
```bash
artillery run ../artillery.yaml --count 10 --num 5
```

## Konfigurasi Test

### Mengubah Target
Edit `config.target` di artillery.yaml:
- Local: `http://localhost:8000`
- Staging: `https://staging.muncak.id`
- Production: `https://muncak.id`

### Mengubah Stream ID
Edit `variables.streamId` sesuai dengan stream yang ingin ditest.

### Mengubah Load
Edit `phases` untuk mengatur:
- `duration`: Durasi fase (detik)
- `arrivalRate`: Request per detik

## Metrics yang Diukur

- **Response Time**: p50, p95, p99
- **Throughput**: Requests per second
- **Error Rate**: Persentase error
- **Concurrent Users**: Jumlah user bersamaan

## Requirements

Pastikan sebelum testing:
1. Laravel app running: `php artisan serve`
2. Reverb running: `php artisan reverb:start`
3. Stream dengan ID yang ditest sudah ada dan live

## Contoh Output

```
All VUs finished. Total time: 6 minutes, 30 seconds

Summary:
  Scenarios launched:  1500
  Scenarios completed: 1485
  Requests completed:  4500
  Mean response/sec:   11.54
  Response time (msec):
    min: 12
    max: 1543
    median: 45
    p95: 234
    p99: 567
```
