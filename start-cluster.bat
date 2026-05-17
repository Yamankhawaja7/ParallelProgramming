@echo off
echo ===================================================
echo Starting Parallel E-Commerce Cluster (4 Servers)
echo ===================================================
start "Node 1 (Port 8000)" php artisan serve --port=8000
start "Node 2 (Port 8001)" php artisan serve --port=8001
start "Node 3 (Port 8002)" php artisan serve --port=8002
start "Node 4 (Port 8003)" php artisan serve --port=8003
echo Cluster started! You can now run k6 load tests.
pause
