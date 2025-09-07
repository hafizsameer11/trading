# 🚀 Live Market Data Generator - Usage Guide

## ✅ **Complete Implementation Ready!**

Your professional live market data generator is now fully implemented and working! Here's how to use it:

## 🚀 **Quick Start**

### **1. Start the Generator**
```bash
# In your terminal, run this command to start generating live data
php artisan market:generate-live
```

### **2. The Generator Will:**
- ✅ Generate realistic price movements for all active pairs
- ✅ Create candles for all timeframes (5s, 10s, 15s, 30s, 1m, 2m, 5m, 10m, 15m, 30m, 1h, 2h, 4h)
- ✅ Apply system controls (trend settings, win rate management)
- ✅ Save all data to database
- ✅ Show stats every 60 seconds

### **3. Stop the Generator**
```bash
# Press Ctrl+C to stop gracefully
# Or use:
php artisan market:generate-live --stop
```

## 📊 **What's Generated**

### **Realistic Market Data:**
- **Geometric Brownian Motion**: Mathematical price movements
- **Time-based Volatility**: Higher during market hours
- **Volume Correlation**: Volume matches price movements
- **Natural Patterns**: Proper OHLC relationships

### **System Control Integration:**
- **Trend Control**: Morning/Afternoon/Evening trends
- **Win Rate Management**: Automatic adjustment to maintain target win rates
- **Subtle Manipulation**: Looks like real exchange data

### **Multi-Timeframe Support:**
- **All Timeframes**: 5s, 10s, 15s, 30s, 1m, 2m, 5m, 10m, 15m, 30m, 1h, 2h, 4h
- **Real-time Generation**: Every second
- **Database Storage**: All candles saved for historical access

## 🎮 **System Controls**

### **Configure Market Behavior:**
1. **Go to Admin Panel → System Controls**
2. **Set Market Trends**:
   - Morning: UP/DOWN/SIDEWAYS
   - Afternoon: UP/DOWN/SIDEWAYS  
   - Evening: UP/DOWN/SIDEWAYS
3. **Set Trend Strength**: 1.0 (subtle) to 10.0 (strong)
4. **Set Win Rate**: Target percentage for daily wins
5. **Set Time Ranges**: Custom session times

### **Changes Apply Immediately:**
- ✅ No restart required
- ✅ Live monitoring via API
- ✅ Automatic session switching

## 📡 **API Endpoints**

### **Get All Pairs Data:**
```bash
GET /api/market-data/all-pairs
```

### **Get Bulk Candles (Initial Load):**
```bash
GET /api/market-data/bulk-candles?pair_id=1&timeframes=5s,10s,15s,30s,1m,2m,5m,10m,15m,30m,1h,2h,4h&from=2025-01-01&to=now&limit=500
```

### **Get Latest Candles (Real-time Updates):**
```bash
GET /api/market-data/latest-candles?pair_id=1&timeframes=1m,5m,15m,1h
```

### **Get Current Price:**
```bash
GET /api/market-data/current-price?pair_id=1
```

### **Get System Status:**
```bash
GET /api/market-data/system-status
```

## 🔄 **Frontend Integration**

### **Use the New Market Data Service:**
```typescript
import { marketDataService } from '../services/marketDataService';

// Get initial data for charts
const initialData = await marketDataService.getInitialChartData(
  pairId,
  ['5s', '10s', '15s', '30s', '1m', '2m', '5m', '10m', '15m', '30m', '1h', '2h', '4h']
);

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
```

## 📈 **Database Schema**

### **Candles Table:**
```sql
- id: Primary key
- pair_id: Foreign key to pairs table
- timeframe: 5s, 10s, 15s, 30s, 1m, 2m, 5m, 10m, 15m, 30m, 1h, 2h, 4h
- open, high, low, close: Price data
- volume: Trading volume
- timestamp: Candle timestamp
- created_at, updated_at: Laravel timestamps
```

## 🎯 **Key Features**

### ✅ **Professional Grade:**
- **Realistic Data**: Looks like real exchange
- **System Control**: Subtle market manipulation
- **Multi-Timeframe**: All timeframes supported
- **Real-time**: Continuous generation
- **Database Storage**: All data persisted

### ✅ **System Integration:**
- **Trend Control**: Apply UP/DOWN/SIDEWAYS trends
- **Win Rate Management**: Automatic adjustment
- **Session-based**: Different trends for different times
- **Live Monitoring**: Real-time status checking

### ✅ **Performance:**
- **Memory Efficient**: Processes all pairs in single loop
- **Database Optimized**: Batch inserts, proper indexes
- **Error Handling**: Continues running on errors
- **Graceful Shutdown**: Clean exit on signals

## 🚀 **Production Ready**

### **Start the Generator:**
```bash
# Run in background
nohup php artisan market:generate-live > market-generator.log 2>&1 &

# Or use process manager
pm2 start "php artisan market:generate-live" --name "market-generator"
```

### **Monitor:**
- **Logs**: Check Laravel logs for errors
- **Database**: Monitor candles table growth
- **API**: Test endpoints for data availability

## 🎉 **You're All Set!**

Your live market data generator is now:
- ✅ **Fully Implemented**: All features working
- ✅ **Professional Grade**: Realistic market simulation
- ✅ **System Controlled**: Admin can control market behavior
- ✅ **Multi-Timeframe**: All timeframes supported
- ✅ **Real-time**: Continuous data generation
- ✅ **Database Stored**: All data persisted
- ✅ **API Ready**: Frontend can consume data
- ✅ **Production Ready**: Can run continuously

**Just run `php artisan market:generate-live` and your professional trading platform will have live, realistic market data with complete system control!** 🚀

