# EDI Reporting and Export Requirements

## Overview
Create comprehensive reporting, export, and printing functionality for the EDI delivery schedule system. Focus on practical formats that support daily production planning and coordination.

## Core Reporting Requirements

### 1. Delivery Schedule Matrix Web Interface
**Page Location**: `?page=delivery_matrix` or `?page=reports`

**Interactive Features:**
- Sortable columns (date, location, PO, part number, quantity)
- Filterable by date range, location, PO number, part number
- Real-time data from delivery_schedules table
- Container calculations (quantity ÷ QPC from part_master table)
- Status indicators (active, shipped, overdue)

**Display Columns:**
- Promise Date
- Ship-To Location (SLB, CNL, CWH)
- PO-Release (e.g., 1067045-236)
- Supplier Item
- Item Description
- Quantity Ordered
- Containers Needed (calculated)
- Status
- Notes

### 2. Location-Specific Reports

**SLB (Shelbyville) Reports:**
- Sequential release pattern handling
- Container optimization focus
- Production planning layout

**CNL/CWH (Canal Winchester) Reports:**
- Duplicate release number handling
- Date-based differentiation
- Multi-part shipment grouping

**Cross-Location Reports:**
- Parts ordered at multiple locations
- Consolidated shipping opportunities
- Resource allocation planning

## Export Functionality Requirements

### 1. Excel Export Templates
**File Format**: .xlsx with pre-formatted templates
**Library**: Use PhpSpreadsheet for Excel generation

**Export Types:**
1. **Daily Production Schedule**
   - Today's deliveries by location
   - Container calculations
   - Print-friendly layout (landscape)
   
2. **Weekly Planning Matrix**  
   - Next 7-14 days of deliveries
   - Grouped by promise date and location
   - Summary totals by location
   
3. **PO-Specific Reports**
   - All releases for selected PO number
   - Historical and future deliveries
   - Release sequence tracking
   
4. **Location-Specific Exports**
   - SLB deliveries only
   - CNL deliveries only  
   - CWH deliveries only
   - Custom location filtering
   
5. **Part-Specific Schedules**
   - All delivery dates for specific parts
   - Multi-location part tracking
   - Quantity variance analysis

### 2. CSV Export (Backup Option)
- Simple comma-separated format
- All data fields included
- Compatible with Excel import

### 3. PDF Reports (Printable)
- Professional headers with company information
- Optimized for 8.5x11 inch paper
- Print-friendly fonts and spacing
- Page breaks at logical points

## Data Requirements

### Database Tables to Include:
- `delivery_schedules` (primary data source)
- `part_master` (for QPC calculations and descriptions)
- `ship_to_locations` (for location code mapping)
- `trading_partners` (for customer information)

### Key Calculations:
```sql
-- Container calculations
ROUND(quantity_ordered / part_master.qpc, 1) as containers_needed

-- Overdue identification  
CASE WHEN promised_date < CURDATE() THEN 'OVERDUE' ELSE 'ACTIVE' END as status

-- Location grouping
CASE 
  WHEN ship_to_location LIKE '%SHELBYVILLE%' THEN 'SLB'
  WHEN ship_to_location LIKE '%CWH%' THEN 'CWH'  
  WHEN ship_to_location LIKE '%CNL%' THEN 'CNL'
END as location_code
```

## Web Interface Implementation

### Navigation Updates
Add to main navigation menu:
- "Delivery Reports" or "Schedule Matrix"
- "Export Data" dropdown with quick export options

### Report Page Layout
```
┌─────────────────────────────────────────────────────────┐
│ Delivery Schedule Matrix                                │
├─────────────────────────────────────────────────────────┤
│ Filters: [Date Range] [Location] [PO Number] [Part]    │
│ Export:  [Excel] [CSV] [PDF] [Print]                   │
├─────────────────────────────────────────────────────────┤
│ Promise Date │ Location │ PO-Release │ Part │ Qty │ Cont│
│ 2025-01-06   │ SLB      │ 1067045-236│ 23466│4480 │22.4 │
│ 2025-01-06   │ SLB      │ 1067045-236│ 28204│4620 │46.2 │
│ 2025-01-06   │ CWH      │ 1067055-132│ 28248│ 120 │ 1.0 │
└─────────────────────────────────────────────────────────┘
```

## Export Button Functionality

### Quick Export Buttons:
- "Today's Schedule" → Excel file with today's deliveries
- "This Week" → Excel file with next 7 days
- "SLB Only" → Location-filtered Excel export
- "CNL Only" → Location-filtered Excel export
- "Custom Range" → Date picker with export options

### File Naming Convention:
- `delivery_schedule_YYYY-MM-DD.xlsx`
- `slb_schedule_YYYY-MM-DD_to_YYYY-MM-DD.xlsx`
- `po_1067045_schedule.xlsx`

## Technical Implementation Notes

### Required Libraries:
```bash
composer require phpoffice/phpspreadsheet  # Excel export
composer require tecnickcom/tcpdf          # PDF generation
```

### Performance Considerations:
- Limit exports to reasonable date ranges (max 90 days)
- Implement pagination for large datasets
- Cache frequently requested reports
- Add loading indicators for export generation

### Security:
- Validate all input parameters
- Sanitize file names
- Implement access controls
- Log all export activities

## Business Logic Handling

### Multi-Location Parts:
- Identify parts ordered at multiple locations
- Provide consolidated view option
- Show location-specific quantities
- Calculate total demand across locations

### Container Optimization:
- Show container utilization percentages
- Identify partial container shipments
- Suggest consolidation opportunities
- Flag quantity variances

### PO Release Complexity:
- Handle SLB sequential releases properly
- Manage CNL/CWH duplicate releases with date differentiation
- Provide clear visual indicators for release patterns
- Group related releases logically

## Part Master Management Interface

### Part Master Input Form (`?page=part_master`)

**Form Fields:**
- Supplier Item (Part Number) - Required, Unique
- Customer Item (Nifco's Part Number) - Optional
- Item Description - Text field
- QPC (Quantity Per Container) - Required, Numeric
- Container Type (CTN, BOX, etc.) - Dropdown
- Unit of Measure (EACH, LB, etc.) - Dropdown
- Weight per Piece - Optional, Decimal
- Active Status - Checkbox

**Form Actions:**
- Add New Part
- Edit Existing Part
- Bulk Import from CSV/Excel
- Export Current Part Master

**Validation Rules:**
- QPC must be greater than 0
- Supplier Item must be unique
- Description cannot be empty
- Numeric fields properly validated

### Part Master List/Search Interface

**Display Features:**
- Searchable/filterable table of all parts
- Sort by part number, description, QPC
- Edit/Delete actions for each part
- Bulk actions (activate/deactivate multiple parts)

**Search Functionality:**
- Search by part number (partial match)
- Search by description (partial match)
- Filter by active/inactive status
- Filter by container type

### Auto-Population from EDI Data

**Smart Part Detection:**
- When processing EDI 862 files, detect new parts not in part_master
- Create "pending parts" list requiring QPC input
- Alert user to parts needing QPC data
- Prevent container calculations until QPC is entered

## Integration Requirements

### Existing System Integration:
- Use current database schema (delivery_schedules, part_master)
- Integrate with existing web interface navigation
- Maintain consistent styling with current application
- Connect with SFTP file processing workflow

### Future Enhancements:
- Email report scheduling
- Automated daily/weekly report generation
- Dashboard widgets with key metrics
- Mobile-responsive report viewing