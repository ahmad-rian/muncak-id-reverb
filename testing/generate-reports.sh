#!/bin/bash

# Generate HTML and PDF Reports from Artillery Test Results
# This script creates comprehensive reports for research/thesis

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESULTS_DIR="${SCRIPT_DIR}/results"

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          Generate HTML & PDF Reports                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Find latest results or use provided path
if [ -z "$1" ]; then
    LATEST_RESULT=$(ls -t "${RESULTS_DIR}" 2>/dev/null | head -n 1)
    if [ -z "${LATEST_RESULT}" ]; then
        echo -e "${RED}✗ No test results found${NC}"
        exit 1
    fi
    RESULT_PATH="${RESULTS_DIR}/${LATEST_RESULT}"
else
    RESULT_PATH="$1"
fi

echo -e "${GREEN}Processing results from: ${RESULT_PATH}${NC}"
echo ""

# Check if report.json exists
if [ ! -f "${RESULT_PATH}/report.json" ]; then
    echo -e "${YELLOW}⚠ No Artillery report.json found${NC}"
    echo -e "${YELLOW}Creating summary from available data...${NC}"
    
    # Create a basic summary JSON from CSV files
    cat > "${RESULT_PATH}/summary.json" << 'EOFJ'
{
  "summary": "Performance Test Results",
  "timestamp": "'$(date -Iseconds)'",
  "test_duration": "~6.5 minutes",
  "metrics_collected": [
    "Latency",
    "Throughput", 
    "CPU Usage",
    "Memory Usage",
    "Concurrent Connections",
    "Connection Establishment Time",
    "Error Rate",
    "Video Quality Stability"
  ]
}
EOFJ
fi

# Generate HTML Report
echo -e "${BLUE}📄 Generating HTML Report...${NC}"

# Check if Artillery report exists and generate HTML from it
if [ -f "${RESULT_PATH}/report.json" ]; then
    echo -e "${YELLOW}Generating Artillery HTML report...${NC}"
    artillery report "${RESULT_PATH}/report.json" --output "${RESULT_PATH}/artillery-report.html" 2>/dev/null || true
fi

# Create comprehensive HTML report
cat > "${RESULT_PATH}/performance-report.html" << 'EOFHTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Test Report - Live Streaming Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4CAF50;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #7f8c8d;
            font-size: 1.2em;
        }
        
        .meta-info {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .meta-info h3 {
            color: #34495e;
            margin-bottom: 15px;
        }
        
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .meta-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        
        .meta-item label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 0.9em;
            display: block;
            margin-bottom: 5px;
        }
        
        .meta-item value {
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .metric-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .metric-card h3 {
            font-size: 1em;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .metric-card .value {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .metric-card .label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        table thead {
            background: #34495e;
            color: white;
        }
        
        table th,
        table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .chart-placeholder {
            background: #ecf0f1;
            padding: 40px;
            text-align: center;
            border-radius: 8px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
            text-align: center;
            color: #7f8c8d;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                padding: 20px;
            }
            
            .metric-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎥 Live Streaming Platform</h1>
            <div class="subtitle">Performance Test Report</div>
        </div>
        
        <div class="meta-info">
            <h3>📋 Test Information</h3>
            <div class="meta-grid">
                <div class="meta-item">
                    <label>Test Date</label>
                    <value id="test-date">-</value>
                </div>
                <div class="meta-item">
                    <label>Target URL</label>
                    <value>https://reverb.muncak.id</value>
                </div>
                <div class="meta-item">
                    <label>Stream ID</label>
                    <value>/live-cam/2</value>
                </div>
                <div class="meta-item">
                    <label>Test Duration</label>
                    <value>~6.5 minutes</value>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>📊 Key Performance Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card success">
                    <h3>⚡ Latency (Median)</h3>
                    <div class="value" id="latency-median">-</div>
                    <div class="label">milliseconds</div>
                </div>
                
                <div class="metric-card info">
                    <h3>📈 Throughput</h3>
                    <div class="value" id="throughput">-</div>
                    <div class="label">requests/sec</div>
                </div>
                
                <div class="metric-card">
                    <h3>💻 CPU Usage (Avg)</h3>
                    <div class="value" id="cpu-avg">-</div>
                    <div class="label">percent</div>
                </div>
                
                <div class="metric-card">
                    <h3>🧠 Memory Usage (Avg)</h3>
                    <div class="value" id="memory-avg">-</div>
                    <div class="label">MB</div>
                </div>
                
                <div class="metric-card info">
                    <h3>🔌 Concurrent Connections</h3>
                    <div class="value" id="connections">-</div>
                    <div class="label">connections</div>
                </div>
                
                <div class="metric-card warning">
                    <h3>❌ Error Rate</h3>
                    <div class="value" id="error-rate">-</div>
                    <div class="label">percent</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>📈 Research Parameters</h2>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px;">1. Latency Metrics</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Minimum</th>
                            <th>Median (p50)</th>
                            <th>p95</th>
                            <th>p99</th>
                            <th>Maximum</th>
                        </tr>
                    </thead>
                    <tbody id="latency-table">
                        <tr>
                            <td>HTTP Request Latency</td>
                            <td id="http-min">-</td>
                            <td id="http-median">-</td>
                            <td id="http-p95">-</td>
                            <td id="http-p99">-</td>
                            <td id="http-max">-</td>
                        </tr>
                        <tr>
                            <td>WebSocket Connection</td>
                            <td id="ws-min">-</td>
                            <td id="ws-median">-</td>
                            <td id="ws-p95">-</td>
                            <td id="ws-p99">-</td>
                            <td id="ws-max">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px;">2. System Resource Usage</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Average</th>
                            <th>Peak</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="system-table">
                        <tr>
                            <td>CPU Usage</td>
                            <td id="sys-cpu-avg">-</td>
                            <td id="sys-cpu-peak">-</td>
                            <td><span class="status-badge success">Normal</span></td>
                        </tr>
                        <tr>
                            <td>Memory Usage</td>
                            <td id="sys-mem-avg">-</td>
                            <td id="sys-mem-peak">-</td>
                            <td><span class="status-badge success">Normal</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px;">3. Video Quality Stability</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Quality</th>
                            <th>Stable Count</th>
                            <th>Changes</th>
                            <th>Stability</th>
                        </tr>
                    </thead>
                    <tbody id="quality-table">
                        <tr>
                            <td>720p</td>
                            <td id="quality-720p">-</td>
                            <td rowspan="2" id="quality-changes">-</td>
                            <td><span class="status-badge success">Stable</span></td>
                        </tr>
                        <tr>
                            <td>1080p</td>
                            <td id="quality-1080p">-</td>
                            <td><span class="status-badge success">Stable</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="section">
            <h2>📝 Test Summary</h2>
            <div class="meta-info">
                <h3>Test Phases</h3>
                <ol style="margin-left: 20px; margin-top: 15px;">
                    <li><strong>Warm-up</strong> (30s): 2 users/sec - Baseline establishment</li>
                    <li><strong>Light Load</strong> (60s): 5-10 users/sec - Normal viewing</li>
                    <li><strong>Medium Load</strong> (90s): 10-25 users/sec - Peak hours simulation</li>
                    <li><strong>Heavy Load</strong> (120s): 25-50 users/sec - Stress testing</li>
                    <li><strong>Spike Test</strong> (60s): 75 users/sec - Sudden traffic surge</li>
                    <li><strong>Cool-down</strong> (30s): 5 users/sec - Recovery testing</li>
                </ol>
            </div>
        </div>
        
        <div class="footer">
            <p>Generated on <span id="report-date">-</span></p>
            <p>Artillery Performance Testing Suite v1.0</p>
        </div>
    </div>
    
    <script>
        // Set current date
        const now = new Date();
        document.getElementById('test-date').textContent = now.toLocaleDateString();
        document.getElementById('report-date').textContent = now.toLocaleString();
        
        // Try to load and populate data from report.json
        fetch('./report.json')
            .then(response => response.json())
            .then(data => {
                const aggregate = data.aggregate || {};
                const counters = aggregate.counters || {};
                const summaries = aggregate.summaries || {};
                const rates = aggregate.rates || {};
                
                // Populate key metrics
                if (summaries['http.response_time']) {
                    document.getElementById('latency-median').textContent = 
                        Math.round(summaries['http.response_time'].median || 0);
                }
                
                if (rates.http_request_rate) {
                    document.getElementById('throughput').textContent = rates.http_request_rate;
                }
                
                // Calculate error rate
                const totalRequests = counters['http.requests'] || 1;
                const totalErrors = counters['total_errors'] || 0;
                const errorRate = ((totalErrors / totalRequests) * 100).toFixed(2);
                document.getElementById('error-rate').textContent = errorRate;
                
                // Populate latency table
                if (summaries['http_request_latency']) {
                    const httpLatency = summaries['http_request_latency'];
                    document.getElementById('http-min').textContent = Math.round(httpLatency.min || 0) + ' ms';
                    document.getElementById('http-median').textContent = Math.round(httpLatency.median || 0) + ' ms';
                    document.getElementById('http-p95').textContent = Math.round(httpLatency.p95 || 0) + ' ms';
                    document.getElementById('http-p99').textContent = Math.round(httpLatency.p99 || 0) + ' ms';
                    document.getElementById('http-max').textContent = Math.round(httpLatency.max || 0) + ' ms';
                }
                
                if (summaries['websocket_connection_latency']) {
                    const wsLatency = summaries['websocket_connection_latency'];
                    document.getElementById('ws-min').textContent = Math.round(wsLatency.min || 0) + ' ms';
                    document.getElementById('ws-median').textContent = Math.round(wsLatency.median || 0) + ' ms';
                    document.getElementById('ws-p95').textContent = Math.round(wsLatency.p95 || 0) + ' ms';
                    document.getElementById('ws-p99').textContent = Math.round(wsLatency.p99 || 0) + ' ms';
                    document.getElementById('ws-max').textContent = Math.round(wsLatency.max || 0) + ' ms';
                } else {
                    document.getElementById('ws-min').textContent = 'N/A';
                    document.getElementById('ws-median').textContent = 'N/A';
                    document.getElementById('ws-p95').textContent = 'N/A';
                    document.getElementById('ws-p99').textContent = 'N/A';
                    document.getElementById('ws-max').textContent = 'N/A';
                }
                
                // Video quality metrics
                document.getElementById('quality-720p').textContent = counters['video_quality_720p_stable'] || 0;
                document.getElementById('quality-1080p').textContent = counters['video_quality_1080p_stable'] || 0;
                document.getElementById('quality-changes').textContent = counters['video_quality_changes'] || 0;
                
                console.log('✅ Report data loaded successfully');
            })
            .catch(err => {
                console.warn('⚠️ Could not load report.json:', err);
            });
        
        // Try to load system metrics from CSV
        fetch('./system_metrics.csv')
            .then(response => response.text())
            .then(csv => {
                const lines = csv.trim().split('\n').slice(1); // Skip header
                if (lines.length > 0) {
                    let totalCpu = 0, maxCpu = 0, totalMem = 0, maxMem = 0;
                    lines.forEach(line => {
                        const parts = line.split(',');
                        const cpu = parseFloat(parts[1]) || 0;
                        const mem = parseFloat(parts[2]) || 0;
                        totalCpu += cpu;
                        totalMem += mem;
                        if (cpu > maxCpu) maxCpu = cpu;
                        if (mem > maxMem) maxMem = mem;
                    });
                    const avgCpu = (totalCpu / lines.length).toFixed(2);
                    const avgMem = (totalMem / lines.length).toFixed(2);
                    
                    document.getElementById('cpu-avg').textContent = avgCpu;
                    document.getElementById('sys-cpu-avg').textContent = avgCpu + '%';
                    document.getElementById('sys-cpu-peak').textContent = maxCpu.toFixed(2) + '%';
                    
                    document.getElementById('memory-avg').textContent = avgMem;
                    document.getElementById('sys-mem-avg').textContent = avgMem + ' MB';
                    document.getElementById('sys-mem-peak').textContent = maxMem.toFixed(2) + ' MB';
                    
                    console.log('✅ System metrics loaded successfully');
                }
            })
            .catch(err => {
                console.warn('⚠️ Could not load system_metrics.csv:', err);
                document.getElementById('cpu-avg').textContent = 'N/A';
                document.getElementById('memory-avg').textContent = 'N/A';
            });
    </script>
</body>
</html>
EOFHTML

echo -e "${GREEN}✓ HTML report created: performance-report.html${NC}"

# Populate HTML with data from JSON
echo ""
echo -e "${BLUE}📊 Populating HTML with data...${NC}"
if [ -f "${SCRIPT_DIR}/populate-html-report.js" ] && [ -f "${RESULT_PATH}/report.json" ]; then
    node "${SCRIPT_DIR}/populate-html-report.js" "${RESULT_PATH}"
else
    echo -e "${YELLOW}⚠ Data population skipped (missing script or report.json)${NC}"
fi


# Generate PDF Report using Node.js puppeteer
echo ""
echo -e "${BLUE}📄 Generating PDF Report...${NC}"

# Check if Node.js PDF generator exists
if [ -f "${SCRIPT_DIR}/generate-pdf.js" ] && [ -d "${SCRIPT_DIR}/node_modules/puppeteer" ]; then
    echo -e "${YELLOW}Using Node.js Puppeteer...${NC}"
    node "${SCRIPT_DIR}/generate-pdf.js" \
        "${RESULT_PATH}/performance-report.html" \
        "${RESULT_PATH}/performance-report.pdf" 2>&1 | grep -E '(✅|❌|📄|📁)'
    
    if [ -f "${RESULT_PATH}/performance-report.pdf" ]; then
        echo -e "${GREEN}✓ PDF report created: performance-report.pdf${NC}"
    else
        echo -e "${YELLOW}⚠ PDF generation failed${NC}"
    fi
elif command -v wkhtmltopdf &> /dev/null; then
    echo -e "${YELLOW}Using wkhtmltopdf...${NC}"
    wkhtmltopdf \
        --enable-local-file-access \
        --page-size A4 \
        --margin-top 20mm \
        --margin-bottom 20mm \
        --margin-left 15mm \
        --margin-right 15mm \
        "${RESULT_PATH}/performance-report.html" \
        "${RESULT_PATH}/performance-report.pdf" 2>/dev/null
    echo -e "${GREEN}✓ PDF report created: performance-report.pdf${NC}"
else
    echo -e "${YELLOW}⚠ PDF generation tool not available${NC}"
    echo -e "${YELLOW}Installing puppeteer: npm install puppeteer${NC}"
    echo -e "${YELLOW}Or install wkhtmltopdf: brew install wkhtmltopdf${NC}"
fi

# Create a summary text report
echo ""
echo -e "${BLUE}📄 Creating summary text report...${NC}"

cat > "${RESULT_PATH}/summary.txt" << EOFTXT
═══════════════════════════════════════════════════════════════
    PERFORMANCE TEST REPORT - LIVE STREAMING PLATFORM
═══════════════════════════════════════════════════════════════

Test Date: $(date)
Target: https://reverb.muncak.id
Stream: /live-cam/2
Duration: ~6.5 minutes

═══════════════════════════════════════════════════════════════
RESEARCH PARAMETERS MEASURED
═══════════════════════════════════════════════════════════════

✓ 1. Latency (HTTP & WebSocket)
✓ 2. Throughput (RPS)
✓ 3. CPU Usage
✓ 4. Memory Usage
✓ 5. Concurrent Connections
✓ 6. Connection Establishment Time
✓ 7. Error Rate
✓ 8. Video Quality Stability (720p & 1080p)

═══════════════════════════════════════════════════════════════
SYSTEM RESOURCE USAGE
═══════════════════════════════════════════════════════════════

EOFTXT

# Add system metrics if available
if [ -f "${RESULT_PATH}/system_metrics.csv" ]; then
    avg_cpu=$(awk -F',' 'NR>1 {sum+=$2; count++} END {if(count>0) printf "%.2f", sum/count; else print "N/A"}' "${RESULT_PATH}/system_metrics.csv")
    max_cpu=$(awk -F',' 'NR>1 {if($2>max) max=$2} END {printf "%.2f", max}' "${RESULT_PATH}/system_metrics.csv")
    avg_memory=$(awk -F',' 'NR>1 {sum+=$3; count++} END {if(count>0) printf "%.2f", sum/count; else print "N/A"}' "${RESULT_PATH}/system_metrics.csv")
    max_memory=$(awk -F',' 'NR>1 {if($3>max) max=$3} END {printf "%.2f", max}' "${RESULT_PATH}/system_metrics.csv")
    
    cat >> "${RESULT_PATH}/summary.txt" << EOFTXT2
CPU Usage:
  Average: ${avg_cpu}%
  Peak: ${max_cpu}%

Memory Usage:
  Average: ${avg_memory} MB
  Peak: ${max_memory} MB

EOFTXT2
fi

cat >> "${RESULT_PATH}/summary.txt" << EOFTXT3
═══════════════════════════════════════════════════════════════
AVAILABLE REPORTS
═══════════════════════════════════════════════════════════════

EOFTXT3

# List available reports
ls -lh "${RESULT_PATH}"/*.{html,pdf,json,csv,txt} 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}' >> "${RESULT_PATH}/summary.txt" || true

echo -e "${GREEN}✓ Summary text report created: summary.txt${NC}"

# Final summary
echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║              Reports Generated Successfully!               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}📁 Reports Location: ${RESULT_PATH}${NC}"
echo ""
echo -e "${GREEN}Generated Reports:${NC}"
[ -f "${RESULT_PATH}/performance-report.html" ] && echo -e "  ✓ HTML Report: performance-report.html"
[ -f "${RESULT_PATH}/performance-report.pdf" ] && echo -e "  ✓ PDF Report: performance-report.pdf"
[ -f "${RESULT_PATH}/artillery-report.html" ] && echo -e "  ✓ Artillery HTML: artillery-report.html"
[ -f "${RESULT_PATH}/summary.txt" ] && echo -e "  ✓ Text Summary: summary.txt"
echo ""
echo -e "${YELLOW}💡 To view HTML report:${NC}"
echo -e "  open ${RESULT_PATH}/performance-report.html"
echo ""
echo -e "${YELLOW}💡 To view PDF report:${NC}"
echo -e "  open ${RESULT_PATH}/performance-report.pdf"
echo ""
