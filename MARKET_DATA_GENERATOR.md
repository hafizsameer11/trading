# 🚀 Live Market Data Generator

## Overview
The Live Market Data Generator is a professional-grade system that generates realistic market data for all trading pairs and timeframes. It integrates with system controls to provide subtle market manipulation while maintaining realistic exchange-like behavior.

## Features

### ✅ **Realistic Market Behavior**
- **Geometric Brownian Motion**: Uses mathematical models for realistic price movements
- **Time-based Volatility**: Different volatility during market hours vs off-hours
- **Volume Correlation**: Volume matches price movements realistically
- **Natural Patterns**: Proper OHLC relationships and candlestick formations

### ✅ **System Control Integration**
- **Trend Control**: Apply UP/DOWN/SIDEWAYS trends for different sessions
- **Trend Strength**: Control how strong the trend influence is (1.0-10.0)
- **Win Rate Management**: Automatically adjust market to maintain desired win rates
- **Session-based**: Different trends for morning, afternoon, and evening sessions

### ✅ **Multi-Timeframe Support**
- **All Timeframes**: 5s, 10s, 15s, 30s, 1m, 2m, 5m, 10m, 15m, 30m, 1h, 2h, 4h
- **Real-time Generation**: Continuous data generation every second
- **Database Storage**: All candles saved to database for historical access
- **Redis Caching**: Fast access to current prices and latest candles

## Usage

### 🚀 **Start the Generator**
```bash
# Start generating live market data
 

# Stop the generator (graceful shutdown)
php artisan market:generate-live --stop
```

### 📊 **API Endpoints**

#### **Get Bulk Candles (Initial Load)**
```bash
GET /api/market-data/bulk-candles?pair_id=1&timeframes=5s,10s,15s,30s,1m,2m,5m,10m,15m,30m,1h,2h,4h&from=2025-01-01&to=now&limit=500
```

#### **Get Latest Candles (Real-time Updates)**
```bash
GET /api/market-data/latest-candles?pair_id=1&timeframes=1m,5m,15m,1h
```

#### **Get Current Price**
```bash
GET /api/market-data/current-price?pair_id=1
```

#### **Get All Pairs Data**
```bash
GET /api/market-data/all-pairs
```

#### **Get System Status**
```bash
GET /api/market-data/system-status
```

## System Controls Integration

### 🎯 **Trend Control**
The generator reads system controls and applies subtle market bias:

- **Morning Session**: Uses `morning_trend` setting
- **Afternoon Session**: Uses `afternoon_trend` setting  
- **Evening Session**: Uses `evening_trend` setting
- **Trend Strength**: Multiplies influence by `trend_strength / 10.0`

### 📈 **Win Rate Management**
Automatically adjusts market to maintain desired win rates:

- **Monitors**: All closed trades for the day
- **Calculates**: Current win rate vs target win rate
- **Adjusts**: Market movement to push win rate towards target
- **Subtle**: Maximum 0.01% price adjustment to avoid detection

## Database Schema

### **Candles Table**
```sql
CREATE TABLE candles (
    id BIGINT PRIMARY KEY,
    pair_id BIGINT,
    timeframe VARCHAR(10),
    open DECIMAL(20,8),
    high DECIMAL(20,8),
    low DECIMAL(20,8),
    close DECIMAL(20,8),
    volume DECIMAL(20,8),
    timestamp TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_pair_timeframe_timestamp (pair_id, timeframe, timestamp),
    INDEX idx_pair_timestamp (pair_id, timestamp),
    INDEX idx_timestamp (timestamp),
    
    FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE CASCADE
);
```

## Performance

### ⚡ **Optimizations**
- **Redis Caching**: Current prices and latest candles cached for 5 minutes
- **Batch Inserts**: Multiple candles inserted in single database operation
- **Memory Efficient**: Processes all pairs and timeframes in single loop
- **Signal Handling**: Graceful shutdown on SIGTERM/SIGINT

### 📊 **Monitoring**
- **Stats Display**: Shows ticks processed, pairs count, memory usage every 60 seconds
- **Error Handling**: Catches and logs errors, continues running
- **Logging**: All system events logged via LoggingService

## Frontend Integration

### 🔄 **Real-time Updates**
```typescript
import { marketDataService } from '../services/marketDataService';

// Subscribe to real-time updates
const unsubscribe = marketDataService.subscribeToUpdates(
  pairId,
  ['1m', '5m', '15m', '1h'],
  (data) => {
    // Update charts with new data
    updateCharts(data.data);
  },
  5000 // Update every 5 seconds
);

// Cleanup
unsubscribe();
```

### 📈 **Initial Data Load**
```typescript
// Get initial data for charts
const initialData = await marketDataService.getInitialChartData(
  pairId,
  ['5s', '10s', '15s', '30s', '1m', '2m', '5m', '10m', '15m', '30m', '1h', '2h', '4h']
);

// Initialize charts with historical data
initializeCharts(initialData);
```

## Configuration

### ⚙️ **System Controls**
Configure market behavior through admin panel:

1. **Go to Admin Panel → System Controls**
2. **Set Market Trends**:
   - Morning: UP/DOWN/SIDEWAYS
   - Afternoon: UP/DOWN/SIDEWAYS
   - Evening: UP/DOWN/SIDEWAYS
3. **Set Trend Strength**: 1.0 (subtle) to 10.0 (strong)
4. **Set Win Rate**: Target percentage for daily wins
5. **Set Time Ranges**: Custom session times

### 🎮 **Real-time Control**
- **Changes Apply Immediately**: No restart required
- **Live Monitoring**: Check system status via API
- **Session Detection**: Automatic session switching based on time

## Troubleshooting

### 🔧 **Common Issues**

#### **Generator Not Starting**
```bash
# Check if Redis is running
redis-cli ping

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### **No Data Generated**
```bash
# Check if pairs are active
php artisan tinker
>>> App\Models\Pair::where('is_active', true)->count();

# Check system controls
>>> App\Models\SystemControl::instance();
```

#### **Performance Issues**
```bash
# Monitor memory usage
php artisan market:generate-live
# Watch the stats output every 60 seconds

# Check database performance
EXPLAIN SELECT * FROM candles WHERE pair_id = 1 AND timeframe = '1m' ORDER BY timestamp DESC LIMIT 100;
```

## Production Deployment

### 🚀 **Recommended Setup**
```bash
# Use process manager (PM2, Supervisor, etc.)
pm2 start "php artisan market:generate-live" --name "market-generator"

# Or use systemd service
sudo systemctl enable market-generator
sudo systemctl start market-generator
```

### 📊 **Monitoring**
- **Logs**: Check Laravel logs for errors
- **Redis**: Monitor Redis memory usage
- **Database**: Monitor candles table growth
- **API**: Monitor API response times

## Security

### 🔒 **Access Control**
- **API Endpoints**: Public (no authentication required for market data)
- **Admin Controls**: Protected by authentication
- **Database**: Standard Laravel security practices

### 🛡️ **Data Integrity**
- **Price Bounds**: Prices stay within pair min/max limits
- **Volume Validation**: Realistic volume generation
- **Timestamp Accuracy**: Precise timestamp alignment

---

**🎉 The Live Market Data Generator provides professional-grade market simulation with complete control over market behavior while maintaining realistic exchange-like appearance!**

