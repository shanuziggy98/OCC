/**
 * Google Apps Script - Daily Occupancy Record Saver
 * Triggers at 8:00 AM JST every day to save occupancy data
 */

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
  // Your trigger URL
  SAVE_URL: 'https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025',
  
  // How many days of data to save (1 = today only, 7 = last 7 days)
  DAYS_TO_SAVE: 1
};

/**
 * Main function to save daily occupancy
 * This will be triggered automatically at 8:00 AM
 */
function saveDailyOccupancy() {
  const timestamp = new Date().toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' });
  console.log(`[${timestamp}] Starting daily occupancy save...`);
  
  try {
    // Build the URL with parameters
    const url = `${CONFIG.SAVE_URL}&days=${CONFIG.DAYS_TO_SAVE}`;
    
    // Make the HTTP request
    console.log(`Calling: ${url}`);
    const response = UrlFetchApp.fetch(url, {
      'method': 'get',
      'followRedirects': true,
      'muteHttpExceptions': true
    });
    
    const responseCode = response.getResponseCode();
    const responseContent = response.getContentText();
    
    console.log(`Response Code: ${responseCode}`);
    
    if (responseCode === 200) {
      console.log('✅ Daily occupancy saved successfully');
      
      try {
        const jsonResponse = JSON.parse(responseContent);
        console.log('Response:', jsonResponse);
        
        // Log to spreadsheet
        logResult(timestamp, 'SUCCESS', responseCode, jsonResponse);
        
      } catch (e) {
        console.log('Response (not JSON):', responseContent.substring(0, 200));
        logResult(timestamp, 'SUCCESS', responseCode, { message: 'Saved' });
      }
      
    } else {
      console.error(`❌ HTTP Error: ${responseCode}`);
      console.error('Response:', responseContent.substring(0, 200));
      logResult(timestamp, 'ERROR', responseCode, { error: `HTTP ${responseCode}` });
    }
    
  } catch (error) {
    console.error(`❌ Error: ${error.message}`);
    logResult(timestamp, 'ERROR', 0, { error: error.message });
  }
}

/**
 * Log results to spreadsheet
 */
function logResult(timestamp, status, httpCode, data) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let logSheet = ss.getSheetByName('Daily OCC Log');
    
    if (!logSheet) {
      logSheet = ss.insertSheet('Daily OCC Log');
      logSheet.appendRow(['Timestamp', 'Status', 'HTTP Code', 'Details']);
      logSheet.getRange('A1:D1').setFontWeight('bold').setBackground('#4285f4').setFontColor('#ffffff');
      logSheet.setFrozenRows(1);
    }
    
    const detailsText = typeof data === 'string' ? data : JSON.stringify(data);
    logSheet.appendRow([timestamp, status, httpCode, detailsText]);
    
    const lastRow = logSheet.getLastRow();
    const statusCell = logSheet.getRange(lastRow, 2);
    
    if (status === 'SUCCESS') {
      statusCell.setBackground('#d9ead3').setFontColor('#38761d');
    } else {
      statusCell.setBackground('#f4cccc').setFontColor('#cc0000');
    }
    
    logSheet.autoResizeColumns(1, 4);
    console.log('Logged to spreadsheet');
  } catch (e) {
    console.error('Failed to log:', e.message);
  }
}

/**
 * Manual test function
 */
function testSaveOccupancy() {
  console.log('=== MANUAL TEST ===');
  saveDailyOccupancy();
}

/**
 * Setup automatic trigger - runs at 8:00 AM JST every day
 */
function setupDailyTrigger() {
  // Remove existing triggers
  removeDailyTrigger();
  
  // Create new trigger at 8 AM JST
  ScriptApp.newTrigger('saveDailyOccupancy')
    .timeBased()
    .everyDays(1)
    .atHour(8)
    .inTimezone('Asia/Tokyo')
    .create();
  
  console.log('✅ Daily trigger created - will run at 8:00 AM JST');
}

/**
 * Remove the daily trigger
 */
function removeDailyTrigger() {
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(trigger => {
    if (trigger.getHandlerFunction() === 'saveDailyOccupancy') {
      ScriptApp.deleteTrigger(trigger);
      console.log('Removed existing trigger');
    }
  });
}

/**
 * List current triggers
 */
function listTriggers() {
  const triggers = ScriptApp.getProjectTriggers();
  console.log(`Total triggers: ${triggers.length}`);
  
  triggers.forEach(trigger => {
    console.log('---');
    console.log('Function:', trigger.getHandlerFunction());
    console.log('Trigger ID:', trigger.getUniqueId());
    console.log('Event Type:', trigger.getEventType());
  });
}

/**
 * Save last 30 days of data (for initial setup)
 */
function saveHistoricalData() {
  console.log('=== Saving 30 days of historical data ===');
  const url = `${CONFIG.SAVE_URL}&days=30`;
  
  try {
    const response = UrlFetchApp.fetch(url, {
      'method': 'get',
      'muteHttpExceptions': true
    });
    
    const responseCode = response.getResponseCode();
    const responseContent = response.getContentText();
    
    console.log(`Response Code: ${responseCode}`);
    console.log('Response:', responseContent);
    
    if (responseCode === 200) {
      const data = JSON.parse(responseContent);
      console.log('✅ Historical data saved successfully');
      console.log(`Records saved: ${data.records_saved}`);
      console.log(`Records updated: ${data.records_updated}`);
    }
  } catch (error) {
    console.error('Error:', error.message);
  }
}
