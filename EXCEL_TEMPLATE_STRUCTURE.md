# Sprint Estimator V4 - Excel Import Template

## 📋 Template Structure

The Excel template allows bulk import of projects, features, and user stories into Sprint Estimator V4.

### Column Structure

| Column | Field Name | Description | Required | Example |
|--------|------------|-------------|----------|---------|
| A | Project Name | Name of the project | Yes | "Mobile App Redesign" |
| B | Project Description | Brief description | No | "Redesign mobile app UI/UX" |
| C | Feature Name | Name of the feature | Yes | "User Authentication" |
| D | Org Productivity | Organization productivity value | Yes | 2.0 |
| E | Man Days Hours | Hours per man day | Yes | 8.0 |
| F | Feature Start Date | Estimated start date (YYYY-MM-DD) | No | 2025-01-15 |
| G | Feature End Date | Target end date (YYYY-MM-DD) | No | 2025-02-28 |
| H | Story Title | User story title | Yes | "Create login screen" |
| I | Story Hours | Estimated hours for story | Yes | 16 |
| J | Story Start Date | Story estimated start (YYYY-MM-DD) | No | 2025-01-15 |
| K | Story End Date | Story target end (YYYY-MM-DD) | No | 2025-01-20 |

---

## 📝 Excel Template Example

```
Project Name | Project Description | Feature Name | Org Prod | MD Hours | Feature Start | Feature End | Story Title | Story Hours | Story Start | Story End
------------|-------------------|--------------|----------|----------|---------------|-------------|-------------|-------------|-------------|------------
E-Commerce | Online shop V2.0 | User Auth | 2 | 8 | 2025-01-10 | 2025-02-15 | Login page | 16 | 2025-01-10 | 2025-01-15
E-Commerce | Online shop V2.0 | User Auth | 2 | 8 | 2025-01-10 | 2025-02-15 | Registration | 24 | 2025-01-16 | 2025-01-22
E-Commerce | Online shop V2.0 | User Auth | 2 | 8 | 2025-01-10 | 2025-02-15 | Password reset | 8 | 2025-01-23 | 2025-01-25
E-Commerce | Online shop V2.0 | Product Catalog | 2 | 8 | 2025-02-01 | 2025-03-15 | List products | 20 | 2025-02-01 | 2025-02-08
E-Commerce | Online shop V2.0 | Product Catalog | 2 | 8 | 2025-02-01 | 2025-03-15 | Search feature | 32 | 2025-02-09 | 2025-02-20
Mobile App | Customer mobile app | Dashboard | 1.8 | 8 | 2025-01-20 | 2025-02-28 | Home screen | 12 | 2025-01-20 | 2025-01-24
Mobile App | Customer mobile app | Dashboard | 1.8 | 8 | 2025-01-20 | 2025-02-28 | Charts widget | 16 | 2025-01-25 | 2025-01-30
```

---

## 🎯 Data Grouping Rules

### How Projects and Features are Grouped:

1. **Same Project Name** = Stories belong to same project
2. **Same Feature Name within Project** = Stories belong to same feature
3. **Each unique Feature** gets its org_productivity and man_days_hours from first occurrence
4. **Feature Dates** taken from first occurrence of feature

### Example:
```
Row 1: Project A | Feature X | Story 1 → Creates Project A, Feature X, adds Story 1
Row 2: Project A | Feature X | Story 2 → Adds Story 2 to existing Feature X
Row 3: Project A | Feature Y | Story 3 → Creates Feature Y in Project A, adds Story 3
Row 4: Project B | Feature Z | Story 4 → Creates new Project B, Feature Z, adds Story 4
```

---

## ✅ Validation Rules

1. **Project Name**: Cannot be empty
2. **Feature Name**: Cannot be empty  
3. **Org Productivity**: Must be > 0 (typically 1.0 - 3.0)
4. **Man Days Hours**: Must be > 0 (typically 6-10)
5. **Story Title**: Cannot be empty
6. **Story Hours**: Must be > 0
7. **Date Format**: Must be YYYY-MM-DD or empty
8. **Story Dates**: Must fall within feature date range (if dates provided)

---

## 🚀 Import Process

1. Download Excel template
2. Fill in data following structure
3. Save as .xlsx or .csv
4. Go to Dashboard → Click "Import from Excel"
5. Select file and upload
6. Review validation results
7. Confirm import

---

## 📊 Auto-Calculated Fields

The following fields are calculated automatically:

- **Story Man Days**: hours ÷ man_days_hours
- **Story Points**: man_days × org_productivity
- **Total Story Points per Feature**: Sum of all story points
- **Total Man Days per Feature**: Sum of all story man days
- **Phase Breakdown**: Auto-calculated (15% req, 15% design, 30% dev, 25% test, 15% PM)

---

## ⚠️ Important Notes

1. **Dates are Optional**: You can leave date columns empty
2. **Duplicate Detection**: Projects with same name will be merged
3. **Feature Settings**: Use same org_productivity and man_days_hours for all stories in a feature
4. **Row Order**: Group stories by feature for better organization
5. **Story Count**: Each feature must have at least 1 story

---

## 💡 Best Practices

### Organizing Your Data:

```
✅ GOOD:
Project A | Feature 1 | Story 1
Project A | Feature 1 | Story 2
Project A | Feature 1 | Story 3
Project A | Feature 2 | Story 4
Project A | Feature 2 | Story 5
Project B | Feature 3 | Story 6

❌ AVOID:
Project A | Feature 1 | Story 1
Project B | Feature 3 | Story 6
Project A | Feature 1 | Story 2  ← Scattered organization
Project A | Feature 2 | Story 4
```

### Date Planning:

```
Feature: Jan 10 - Feb 15
  ✅ Story 1: Jan 10 - Jan 15 (within range)
  ✅ Story 2: Jan 16 - Jan 22 (within range)
  ❌ Story 3: Jan 5 - Jan 9 (starts before feature)
  ❌ Story 4: Feb 10 - Feb 20 (ends after feature)
```

---

## 📁 Sample Data

See `excel_template_sample.xlsx` for a complete working example with:
- 2 Projects
- 5 Features
- 15 User Stories
- Proper date sequencing
- Varied story sizes

---

## 🔄 Update Existing Data

**Import Behavior**:
- **New Project Name**: Creates new project
- **Existing Project Name**: Adds features to existing project
- **Duplicate Feature**: Creates separate feature with same name
- **Productivity Data**: Not imported (must be entered manually)

**To Replace Existing Data**:
1. Delete old project from dashboard
2. Import new Excel data
