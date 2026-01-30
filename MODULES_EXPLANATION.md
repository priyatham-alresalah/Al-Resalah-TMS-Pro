# Complete Module Explanation - Training Management System

This document provides a comprehensive explanation of every module in the Training Management System, their purpose, functionality, workflows, and interconnections.

---

## 📊 **1. DASHBOARD MODULE**

### **Purpose**
Central hub displaying key metrics, statistics, and quick access to important information based on user role.

### **Key Features**
- **Role-Based Views**: Different dashboards for Admin, BDM, BDO, Trainer, Accounts, Client, Candidate
- **Real-Time Metrics**: 
  - This month's inquiries, trainings, invoices
  - Outstanding invoices and revenue
  - Conversion rates (inquiry → quotation → training)
  - Completed trainings vs certificates issued (bottleneck detection)
  - Overdue invoices
- **Quick Actions**: Direct links to create new records
- **Performance Optimization**: Uses caching and aggregated queries to prevent timeout

### **Access Control**
- **All authenticated users** can access the dashboard
- Metrics filtered by role (e.g., BDO sees only their inquiries)

### **Location**
- `pages/dashboard.php`

---

## 👥 **2. MASTERS MODULE**

Masters are foundational data that other modules reference. Only Admin and Accounts roles can manage masters.

### **2.1. USERS MODULE**

#### **Purpose**
Manage system users, roles, and access permissions.

#### **Key Features**
- **User Management**: Create, edit, activate/deactivate users
- **Role Assignment**: Assign roles (admin, bdm, bdo, trainer, accounts, client, candidate, coordinator)
- **Profile Sync**: Sync users from Supabase Auth to profiles table
- **Access Control**: Admin-only access
- **User Status**: Active/Inactive toggle

#### **Workflow**
1. Admin creates user → User receives credentials
2. User logs in → Profile created/updated
3. Admin assigns role → User gets module access based on RBAC

#### **Database Tables**
- `auth.users` (Supabase Auth)
- `profiles` (Extended user information)
- `roles`, `permissions`, `role_permissions` (RBAC)

#### **Location**
- `pages/users.php`
- `pages/user_create.php`
- `pages/user_edit.php`
- `api/users/create.php`
- `api/users/update.php`
- `api/users/sync_profiles.php`

---

### **2.2. CLIENTS MODULE**

#### **Purpose**
Manage client companies/organizations that request training services.

#### **Key Features**
- **Client CRUD**: Create, view, edit client information
- **Client Details**: Company name, contact person, email, phone, address
- **Ownership Rules**: Non-admin users see only clients they created
- **Client Portal**: Clients can log in to view their inquiries, quotations, trainings, invoices
- **Integration**: Linked to inquiries, quotations, trainings, invoices

#### **Workflow**
1. BDO/BDM creates client → Client record created
2. Client can log in via client portal → View their data
3. Client used in inquiries → Quotations → Trainings → Invoices

#### **Database Tables**
- `clients`

#### **Location**
- `pages/clients.php`
- `pages/client_create.php`
- `pages/client_edit.php`
- `api/clients/create.php`
- `api/clients/update.php`
- `client_portal/` (Client-facing portal)

---

### **2.3. CANDIDATES MODULE**

#### **Purpose**
Master list of training participants (individuals who attend training sessions).

#### **Key Features**
- **Candidate CRUD**: Create, view, edit candidate information
- **Candidate Details**: Full name, email, phone, address, client association
- **Client Association**: Candidates can be linked to a client (for corporate training)
- **Individual Candidates**: Can exist without client (individual training)
- **Integration**: Used in training assignments, certificate issuance

#### **Workflow**
1. Create candidate → Can be linked to client or standalone
2. Assign to training → Candidate attends training session
3. Certificate issued → Certificate linked to candidate

#### **Database Tables**
- `candidates` (with `client_id` foreign key)

#### **Location**
- `pages/candidates.php`
- `pages/candidate_create.php`
- `pages/candidate_edit.php`
- `api/candidates/create.php`
- `api/candidates/update.php`
- `candidate_portal/` (Candidate-facing portal)

---

### **2.4. TRAINING MASTER MODULE**

#### **Purpose**
Master catalog of training courses available in the system.

#### **Key Features**
- **Course Management**: Add, edit, delete training courses
- **Course Details**: Course name, duration (e.g., "1 Day", "2 Days")
- **Standardization**: Ensures consistent course names across the system
- **Integration**: Referenced in inquiries, quotations, trainings, certificates

#### **Workflow**
1. Admin/Accounts adds course → Course available in dropdowns
2. Course used in inquiry → Quotation → Training → Certificate

#### **Database Tables**
- `training_master`

#### **Location**
- `pages/training_master.php`
- `api/training_master/create.php`
- `api/training_master/update.php`
- `api/training_master/delete.php`

---

## 🔄 **3. OPERATIONS MODULE**

Operations modules handle the core business workflow from inquiry to training completion.

### **3.1. INQUIRIES MODULE**

#### **Purpose**
Initial customer requests for training services. Entry point of the business workflow.

#### **Key Features**
- **Inquiry Creation**: Create new training inquiries with client, course, and requirements
- **Status Tracking**: `new` → `quoted` → `closed`
- **Grouping**: Multiple inquiries from same client grouped together
- **Quote Generation**: Convert inquiries to quotations
- **Client Response**: Clients can accept/reject/request requote
- **PDF Download**: Download quote PDFs
- **Email Integration**: Send quotes via email

#### **Workflow**
```
1. BDO/BDM creates inquiry (status: 'new')
   ↓
2. BDO creates quote from inquiry → Inquiry status: 'quoted'
   ↓
3. Client views quote → Accepts/Rejects/Requests Requote
   ↓
4. If accepted → Inquiry status: 'closed' → Moves to Quotations module
   ↓
5. If rejected → Inquiry status: 'closed'
   ↓
6. If requote → Inquiry status: 'new' (cycle repeats)
```

#### **Status Flow**
- **`new`**: Newly created inquiry, not yet quoted
- **`quoted`**: Quote has been created and sent to client
- **`closed`**: Client responded (accepted/rejected) or inquiry closed

#### **Database Tables**
- `inquiries`
- `quotations` (created from inquiries)

#### **Location**
- `pages/inquiries.php` (List all inquiries)
- `pages/inquiry_create.php` (Create new inquiry)
- `pages/inquiry_edit.php` (Edit inquiry)
- `pages/inquiry_view.php` (View inquiry details and respond)
- `pages/inquiry_quote.php` (Create quote from inquiries)
- `api/inquiries/create.php`
- `api/inquiries/update.php`
- `api/inquiries/create_quote.php` (Convert to quotation)
- `api/inquiries/respond_quote.php` (Client response)
- `api/inquiries/download_quote.php` (PDF download)

---

### **3.2. QUOTATIONS MODULE**

#### **Purpose**
Manage training quotations, approvals, and client acceptance workflow.

#### **Key Features**
- **Quotation Creation**: Created from inquiries with pricing details
- **Approval Workflow**: 
  - BDO creates as `draft` → Submits for approval (`pending_approval`)
  - BDM approves (`approved`) or rejects (`rejected`)
  - Client accepts (`accepted`)
- **Role-Based Views**:
  - **BDO**: Sees only their quotations
  - **BDM**: Sees `pending_approval`, `approved`, `accepted`, `rejected`
  - **Admin**: Sees all quotations
- **Next Actions**: 
  - `accepted` quotations → Upload LPO or Schedule Training
- **PDF Generation**: Quote PDFs with course details, pricing, VAT

#### **Workflow**
```
1. Quotation created from inquiry (status: 'draft' or 'pending_approval')
   ↓
2. BDM reviews → Approves ('approved') or Rejects ('rejected')
   ↓
3. Client accepts ('accepted')
   ↓
4. Upload LPO → Client Orders module (status: 'pending')
   ↓
5. Verify LPO → LPO status: 'verified'
   ↓
6. Schedule Training → Trainings module (ONLY after LPO verified)
```

#### **Status Flow**
- **`draft`**: BDO created, not yet submitted
- **`pending_approval`**: Submitted to BDM for approval
- **`approved`**: BDM approved, waiting for client acceptance
- **`accepted`**: Client accepted, ready for LPO/training
- **`rejected`**: BDM rejected the quotation

#### **Database Tables**
- `quotations`
- `inquiries` (linked via `inquiry_id`)

#### **Location**
- `pages/quotations.php`
- `api/quotations/create.php`
- `api/quotations/approve.php`
- `api/quotations/accept.php`

---

### **3.3. CLIENT ORDERS (LPO) MODULE**

#### **Purpose**
Manage Letter of Purchase Orders (LPO) uploads and verification workflow.

#### **Key Features**
- **LPO Upload**: Upload LPO documents against accepted quotations
- **Verification Workflow**: 
  - Uploaded → Status: `pending`
  - Verified → Status: `verified` (allows training creation)
  - Rejected → Status: `rejected`
- **File Management**: Store and download LPO PDFs/documents
- **Prerequisite Check**: Training cannot be created without verified LPO
- **Audit Trail**: Logs who verified/rejected and when

#### **Workflow**
```
1. Quotation accepted → Upload LPO (status: 'pending')
   ↓
2. Authorized user verifies LPO (status: 'verified')
   ↓
3. Training can now be scheduled
```

#### **Status Flow**
- **`pending`**: LPO uploaded, awaiting verification
- **`verified`**: LPO verified, training can proceed
- **`rejected`**: LPO rejected, needs correction

#### **Database Tables**
- `client_orders` (LPO records)
- `quotations` (linked via `quotation_id`)

#### **Location**
- `pages/client_orders.php`
- `api/client_orders/create.php` (Upload LPO)
- `api/client_orders/verify.php` (Verify/Reject)

---

### **3.4. TRAININGS MODULE**

#### **Purpose**
Schedule and manage training sessions, assign candidates, track completion.

#### **Key Features**
- **Training Scheduling**: Schedule training with date, time, trainer, course
- **Candidate Assignment**: Assign multiple candidates to a training
- **Trainer Assignment**: Assign certified trainers to courses
- **Status Tracking**: `scheduled` → `in_progress` → `completed` → `cancelled`
- **Prerequisites Enforcement**: 
  - Requires accepted quotation
  - Requires verified LPO
  - Trainer must be certified for course
  - Trainer availability check
- **Training Checkpoints**: Automatic creation of workflow checkpoints
- **Document Upload**: Upload training documents (attendance, materials)
- **Strict Workflow**: Training can ONLY be scheduled from accepted quotations with verified LPOs

#### **Workflow**
```
1. Prerequisites met (quotation accepted, LPO verified)
   ↓
2. Schedule training → Select date, time, trainer, course
   ↓
3. Assign candidates → Candidates attend training
   ↓
4. Training completed → Status: 'completed'
   ↓
5. Upload documents → Attendance sheets, materials
   ↓
6. Issue certificates → Certificates module
```

#### **Status Flow**
- **`scheduled`**: Training scheduled, not yet started
- **`in_progress`**: Training currently happening
- **`completed`**: Training finished, ready for certificate
- **`cancelled`**: Training cancelled

#### **Database Tables**
- `trainings`
- `training_candidates` (Many-to-many: training ↔ candidates)
- `training_checkpoints` (Workflow tracking)
- `training_documents` (Uploaded documents)
- `trainer_courses` (Trainer certifications)
- `trainer_availability` (Trainer scheduling)

#### **Location**
- `pages/trainings.php` (List trainings)
- `pages/schedule_training.php` (Schedule new training)
- `pages/training_edit.php` (Edit training)
- `pages/training_candidates.php` (View assigned candidates)
- `pages/training_assign_candidates.php` (Assign candidates)
- `api/trainings/create.php`
- `api/trainings/update.php`
- `api/trainings/schedule.php`
- `api/trainings/assign_candidates.php`
- `api/trainings/convert.php`

---

## 📜 **4. CERTIFICATES MODULE**

#### **Purpose**
Issue, manage, and track training certificates for candidates.

#### **Key Features**
- **Certificate Issuance**: Issue certificates to candidates after training completion
- **Bulk Issuance**: Issue certificates to multiple candidates at once
- **Certificate Numbering**: Sequential certificate numbers per course
- **PDF Generation**: Generate certificate PDFs with candidate details, course, date
- **Prerequisites Enforcement**:
  - Training must be completed
  - All mandatory documents must be uploaded
- **Certificate Revocation**: Revoke certificates if needed
- **Audit Trail**: Log all certificate issuances and revocations
- **Certificate View**: View certificate details and download PDF

#### **Workflow**
```
1. Training completed → All documents uploaded
   ↓
2. Issue certificate → Certificate generated with unique number
   ↓
3. Certificate PDF created → Available for download
   ↓
4. Certificate linked to candidate and training
```

#### **Database Tables**
- `certificates`
- `certificate_issuance_logs` (Audit trail)
- `certificate_counters` (Sequential numbering)
- `document_requirements` (Required documents per course)
- `training_documents` (Uploaded documents)

#### **Location**
- `pages/certificates.php` (List certificates)
- `pages/certificate_create.php` (Create certificate manually)
- `pages/certificate_view.php` (View certificate)
- `pages/certificate_edit.php` (Edit certificate)
- `pages/issue_certificates.php` (Bulk issuance)
- `api/certificates/issue.php`
- `api/certificates/issue_bulk.php`
- `api/certificates/revoke.php`
- `includes/certificate_pdf.php` (PDF generation)

---

## 💰 **5. FINANCE MODULE**

Finance modules handle invoicing and payment tracking.

### **5.1. INVOICES MODULE**

#### **Purpose**
Generate and manage invoices for completed trainings.

#### **Key Features**
- **Invoice Creation**: Create invoices from completed trainings/certificates
- **Invoice Details**: Invoice number, client, course, amount, VAT, due date
- **Status Tracking**: `draft` → `issued` → `unpaid` → `paid` → `overdue`
- **PDF Generation**: Generate invoice PDFs
- **Email Integration**: Send invoices via email
- **Prerequisites**: Requires certificate to be issued
- **Payment Tracking**: Link payments to invoices
- **Payment Allocation**: Allocate partial payments across invoices

#### **Workflow**
```
1. Certificate issued → Create invoice (status: 'draft')
   ↓
2. Issue invoice → Status: 'issued'/'unpaid', PDF generated
   ↓
3. Send to client → Email invoice PDF
   ↓
4. Payment received → Allocate payment → Status: 'paid'
```

#### **Status Flow**
- **`draft`**: Invoice created, not yet issued
- **`issued`**: Invoice sent to client
- **`unpaid`**: Invoice awaiting payment
- **`paid`**: Invoice fully paid
- **`overdue`**: Invoice past due date, unpaid

#### **Database Tables**
- `invoices`
- `payments` (Linked payments)
- `payment_allocations` (Payment-to-invoice allocation)

#### **Location**
- `pages/invoices.php` (List invoices)
- `pages/invoice_edit.php` (Edit invoice)
- `api/invoices/create.php`
- `api/invoices/update.php`
- `api/invoices/download.php` (PDF download)
- `api/invoices/print_pdf.php` (Print PDF)
- `api/invoices/send_email.php` (Email invoice)
- `includes/invoice_pdf.php` (PDF generation)

---

### **5.2. PAYMENTS MODULE**

#### **Purpose**
Record and track payments received from clients against invoices.

#### **Key Features**
- **Payment Recording**: Record payments with amount, date, payment method
- **Payment Allocation**: Allocate payments to specific invoices
- **Partial Payments**: Support partial payments, track remaining balance
- **Payment Methods**: Cash, bank transfer, cheque, etc.
- **Invoice Status Update**: Automatically updates invoice status when fully paid
- **Payment History**: View all payments and their allocations
- **Outstanding Tracking**: See which invoices still have outstanding amounts

#### **Workflow**
```
1. Payment received → Record payment
   ↓
2. Allocate payment to invoice(s) → Update invoice status
   ↓
3. If invoice fully paid → Invoice status: 'paid'
   ↓
4. If partial payment → Track remaining balance
```

#### **Database Tables**
- `payments`
- `payment_allocations` (Payment-to-invoice mapping)
- `invoices` (Updated status)

#### **Location**
- `pages/payments.php`
- `api/payments/create.php`
- `api/payments/allocate.php`

---

## 📊 **6. REPORTS MODULE**

#### **Purpose**
Generate business reports, analytics, and performance metrics.

#### **Key Features**
- **User Performance**: Statistics per user (inquiries, trainings, certificates, invoices)
- **Revenue Reports**: Revenue by period, client, course
- **Conversion Metrics**: Inquiry → Quotation → Training conversion rates
- **Training Statistics**: Trainings by status, trainer, course
- **Certificate Reports**: Certificates issued by course, period
- **Invoice Reports**: Outstanding invoices, overdue invoices, revenue
- **Client Reports**: Client activity, training history
- **Export Capabilities**: Export reports to CSV/PDF (if implemented)

#### **Access Control**
- **Admin only** access

#### **Database Tables**
- All tables (aggregated queries)

#### **Location**
- `pages/reports.php`

---

## 🔗 **COMPLETE WORKFLOW INTEGRATION**

### **End-to-End Business Flow**

```
1. INQUIRY (new)
   ↓ BDO creates inquiry
   
2. QUOTATION (draft → pending_approval → approved → accepted)
   ↓ BDO creates quote → BDM approves → Client accepts
   
3. CLIENT ORDER / LPO (pending → verified)
   ↓ Upload LPO → Verify LPO
   
4. TRAINING (scheduled → completed)
   ↓ Schedule training → Assign candidates → Complete training
   
5. CERTIFICATE (issued)
   ↓ Issue certificate after training completion
   
6. INVOICE (issued → unpaid → paid)
   ↓ Create invoice → Send to client → Receive payment
   
7. PAYMENT (recorded → allocated)
   ↓ Record payment → Allocate to invoice
```

### **Workflow Enforcement Rules**

- ❌ **Cannot create training** without accepted quotation
- ❌ **Cannot create training** without verified LPO
- ❌ **Cannot issue certificate** without completed training
- ❌ **Cannot issue certificate** without mandatory documents uploaded
- ❌ **Cannot create invoice** without certificate issued
- ❌ **Cannot assign trainer** who is not certified for the course
- ❌ **Cannot double-book trainer** (availability check)

---

## 🔐 **ROLE-BASED ACCESS CONTROL (RBAC)**

### **Roles and Module Access**

| Role | Dashboard | Masters | Operations | Certificates | Finance | Reports |
|------|----------|---------|------------|--------------|---------|---------|
| **Admin** | ✅ All | ✅ All | ✅ All | ✅ All | ✅ All | ✅ All |
| **Accounts** | ✅ All | ✅ All | ✅ Limited | ✅ View | ✅ All | ✅ All |
| **BDM** | ✅ Filtered | ❌ | ✅ Inquiries, Quotations | ❌ | ❌ | ❌ |
| **BDO** | ✅ Own | ❌ | ✅ Own Inquiries | ❌ | ❌ | ❌ |
| **Trainer** | ✅ Assigned | ❌ | ✅ Assigned Trainings | ✅ View | ❌ | ❌ |
| **Client** | ✅ Own | ❌ | ✅ Own Data | ✅ Own | ✅ Own | ❌ |
| **Candidate** | ✅ Own | ❌ | ❌ | ✅ Own | ❌ | ❌ |
| **Coordinator** | ✅ Filtered | ❌ | ✅ Trainings | ✅ View | ❌ | ❌ |

---

## 📁 **PORTAL MODULES**

### **Client Portal** (`client_portal/`)
- **Login**: `login.php`
- **Dashboard**: View inquiries, quotations, trainings, invoices
- **Profile**: Update client information
- **Inquiry**: Submit new training inquiries

### **Candidate Portal** (`candidate_portal/`)
- **Login**: `login.php`
- **Dashboard**: View assigned trainings, certificates
- **Profile**: Update candidate information
- **Inquiry**: Submit individual training inquiries

---

## 🔧 **TECHNICAL FEATURES**

### **Security**
- CSRF protection on all forms
- RBAC permission checks
- Input validation and sanitization
- Secure session management
- Audit logging for critical actions

### **Performance**
- Caching for frequently accessed data
- Pagination for large datasets
- Optimized database queries
- Batch fetching to prevent N+1 queries

### **PDF Generation**
- Quote PDFs (FPDF)
- Invoice PDFs (FPDF)
- Certificate PDFs (FPDF)
- Centralized PDF library loading

### **Error Handling**
- Comprehensive error logging
- User-friendly error messages
- Graceful degradation (e.g., PDF generation)

---

## 📝 **SUMMARY**

This Training Management System provides a complete workflow from initial customer inquiry through training delivery, certificate issuance, invoicing, and payment collection. Each module is designed with role-based access control, workflow enforcement, audit trails, and integration points to ensure data integrity and business process compliance.

**Key Strengths:**
- ✅ Complete end-to-end workflow
- ✅ Role-based access control
- ✅ Workflow enforcement (prevents skipping steps)
- ✅ Audit logging
- ✅ PDF generation for quotes, invoices, certificates
- ✅ Client and candidate portals
- ✅ Performance optimization (caching, pagination)

**Modules Count:** 15+ modules across 6 main categories (Dashboard, Masters, Operations, Certificates, Finance, Reports)
