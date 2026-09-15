<?php
declare(strict_types=1);
require_once __DIR__ . '/attendance-auto-checkout.php';

function ensure_employee_tables(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS employees (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_code VARCHAR(20) NOT NULL, name VARCHAR(160) NOT NULL, phone VARCHAR(40) NOT NULL, email VARCHAR(190) NULL, role_position VARCHAR(120) NOT NULL, join_date DATE NOT NULL, salary DECIMAL(12,2) NOT NULL DEFAULT 0, paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', address TEXT NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employees_code_unique (employee_code), KEY employees_status_index (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!database()->query("SHOW COLUMNS FROM employees LIKE 'paid_leave_days'")->fetch()) database()->exec("ALTER TABLE employees ADD paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER salary");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_salaries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, salary_month DATE NOT NULL, basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0, allowances DECIMAL(12,2) NOT NULL DEFAULT 0, deductions DECIMAL(12,2) NOT NULL DEFAULT 0, net_salary DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('pending','paid') NOT NULL DEFAULT 'pending', paid_at DATETIME NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employee_salary_month_unique (employee_id, salary_month), CONSTRAINT employee_salaries_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'amount_paid'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER net_salary");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'receipt_no'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD receipt_no VARCHAR(40) NULL AFTER amount_paid");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'advance_deduction'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD advance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deductions");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'loan_deduction'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD loan_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER advance_deduction");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'unpaid_leave_days'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD unpaid_leave_days DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER loan_deduction");
    if (!database()->query("SHOW COLUMNS FROM employee_salaries LIKE 'attendance_deduction'")->fetch()) database()->exec("ALTER TABLE employee_salaries ADD attendance_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unpaid_leave_days");
    database()->exec("UPDATE employee_salaries SET advance_deduction=CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(notes,'Advance deduction: ',-1),' |',1)) AS DECIMAL(12,2)) WHERE advance_deduction=0 AND notes LIKE '%Advance deduction:%'");
    database()->exec("UPDATE employee_salaries SET loan_deduction=CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(notes,'Loan deduction: ',-1),' |',1)) AS DECIMAL(12,2)) WHERE loan_deduction=0 AND notes LIKE '%Loan deduction:%'");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_advances (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, advance_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, balance DECIMAL(12,2) NOT NULL DEFAULT 0, reason VARCHAR(255) NULL, status ENUM('unsettled','settled') NOT NULL DEFAULT 'unsettled', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), CONSTRAINT employee_advances_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!database()->query("SHOW COLUMNS FROM employee_advances LIKE 'balance'")->fetch()) { database()->exec("ALTER TABLE employee_advances ADD balance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount"); database()->exec("UPDATE employee_advances SET balance=amount WHERE status='unsettled' AND balance=0"); }
    database()->exec("CREATE TABLE IF NOT EXISTS employee_loans (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, loan_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, installment DECIMAL(12,2) NOT NULL DEFAULT 0, balance DECIMAL(12,2) NOT NULL DEFAULT 0, reason VARCHAR(255) NULL, status ENUM('active','settled') NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), CONSTRAINT employee_loans_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_attendance (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, attendance_date DATE NOT NULL, attendance_status ENUM('present','absent','leave','half_day') NOT NULL DEFAULT 'present', check_in TIME NULL, check_out TIME NULL, notes VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employee_attendance_day_unique (employee_id, attendance_date), KEY employee_attendance_date_index (attendance_date), KEY employee_attendance_status_index (attendance_status), CONSTRAINT employee_attendance_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    attendance_auto_checkout(database());
    $ready = true;
}

function employee_input(): array
{
    $status = $_POST['status'] ?? 'active';
    return ['name'=>trim((string)($_POST['name']??'')),'phone'=>trim((string)($_POST['phone']??'')),'email'=>trim((string)($_POST['email']??'')),'role_position'=>trim((string)($_POST['role_position']??'')),'join_date'=>trim((string)($_POST['join_date']??date('Y-m-d'))),'salary'=>(float)($_POST['salary']??0),'paid_leave_days'=>(float)($_POST['paid_leave_days']??0),'status'=>in_array($status,['active','inactive'],true)?$status:'active','address'=>trim((string)($_POST['address']??'')),'notes'=>trim((string)($_POST['notes']??''))];
}
function employee_list(): array { return database()->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll(); }
function employee_options(): array { return database()->query("SELECT id,name,employee_code,salary,paid_leave_days FROM employees WHERE status='active' ORDER BY name")->fetchAll(); }
function employee_all_options(): array { return database()->query('SELECT id,name,employee_code,salary,paid_leave_days FROM employees ORDER BY name')->fetchAll(); }
function employee_find(int $id): ?array { $s=database()->prepare('SELECT * FROM employees WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_validate(array $input): array { $e=[]; if($input['name']==='')$e[]='Employee name is required.'; if($input['phone']==='')$e[]='Phone number is required.'; if($input['role_position']==='')$e[]='Role / position is required.'; $d=DateTime::createFromFormat('Y-m-d',$input['join_date']); if(!$d||$d->format('Y-m-d')!==$input['join_date'])$e[]='Join date is required.'; if($input['salary']<0)$e[]='Salary cannot be negative.'; if($input['paid_leave_days']<0)$e[]='Paid leave days cannot be negative.'; if($input['email']!==''&&!filter_var($input['email'],FILTER_VALIDATE_EMAIL))$e[]='Enter a valid email address.'; return $e; }
function attendance_filters(): array { return ['employee_id'=>(int)($_GET['attendance_employee_id']??0),'status'=>in_array($_GET['attendance_status']??'', ['present','absent','leave','half_day'], true)?$_GET['attendance_status']:'','date_from'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_from']??''))?$_GET['date_from']:'','date_to'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_to']??''))?$_GET['date_to']:'']; }
function attendance_rows(array $filters): array { $where=[];$params=[]; if($filters['employee_id']>0){$where[]='a.employee_id=:employee_id';$params['employee_id']=$filters['employee_id'];} if($filters['status']!==''){$where[]='a.attendance_status=:status';$params['status']=$filters['status'];} if($filters['date_from']!==''){$where[]='a.attendance_date>=:date_from';$params['date_from']=$filters['date_from'];} if($filters['date_to']!==''){$where[]='a.attendance_date<=:date_to';$params['date_to']=$filters['date_to'];} $sql='SELECT a.*,e.name employee_name,e.employee_code FROM employee_attendance a JOIN employees e ON e.id=a.employee_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY a.attendance_date DESC,a.id DESC';$s=database()->prepare($sql);$s->execute($params);return $s->fetchAll(); }
function attendance_find(int $id): ?array { $s=database()->prepare('SELECT * FROM employee_attendance WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_loan_find(int $id): ?array { $s=database()->prepare('SELECT l.*,e.name employee_name,e.employee_code FROM employee_loans l JOIN employees e ON e.id=l.employee_id WHERE l.id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_advance_find(int $id): ?array { $s=database()->prepare('SELECT a.*,e.name employee_name,e.employee_code FROM employee_advances a JOIN employees e ON e.id=a.employee_id WHERE a.id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function salary_attendance_summary(int $employeeId, string $month): array
{
    $start = DateTime::createFromFormat('Y-m-d', $month.'-01');
    if (!$start) return ['calendar_days'=>0,'working_days'=>0,'present_days'=>0,'absent_days'=>0,'leave_days'=>0,'half_days'=>0,'unpaid_days'=>0];
    $end = (clone $start)->modify('last day of this month'); $calendarDays=(int)$end->format('j'); $workingDays=0;
    for($day=clone $start;$day<=$end;$day->modify('+1 day')) if((int)$day->format('N')!==7)$workingDays++;
    $s=database()->prepare("SELECT attendance_status,COUNT(*) total FROM employee_attendance WHERE employee_id=:employee_id AND attendance_date BETWEEN :start AND :end AND DAYOFWEEK(attendance_date) <> 1 GROUP BY attendance_status"); $s->execute(['employee_id'=>$employeeId,'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d')]); $counts=['present_days'=>0,'absent_days'=>0,'leave_days'=>0,'half_days'=>0];
    foreach($s->fetchAll() as $row){$key=['present'=>'present_days','absent'=>'absent_days','leave'=>'leave_days','half_day'=>'half_days'][$row['attendance_status']]??null;if($key)$counts[$key]=(int)$row['total'];}
    $counts['calendar_days']=$calendarDays; $counts['working_days']=$workingDays; $counts['unpaid_days']=$counts['absent_days']+$counts['leave_days']+($counts['half_days']*0.5); return $counts;
}
function salary_financial_summary(int $employeeId): array { $s=database()->prepare("SELECT COALESCE((SELECT SUM(balance) FROM employee_advances WHERE employee_id=:id1 AND status='unsettled'),0) advance_total,COALESCE((SELECT SUM(balance) FROM employee_loans WHERE employee_id=:id2 AND status='active'),0) loan_total");$s->execute(['id1'=>$employeeId,'id2'=>$employeeId]);return $s->fetch()?:['advance_total'=>0,'loan_total'=>0]; }
function salary_find(int $id): ?array { $s=database()->prepare('SELECT s.*,e.name employee_name,e.employee_code FROM employee_salaries s JOIN employees e ON e.id=s.employee_id WHERE s.id=:id LIMIT 1');$s->execute(['id'=>$id]);$row=$s->fetch();return $row?:null; }
function employee_save(array $input, ?int $id=null): void
{
    $pdo=database(); $pdo->beginTransaction();
    try {
        $values=['name'=>$input['name'],'phone'=>$input['phone'],'email'=>$input['email']?:null,'role_position'=>$input['role_position'],'join_date'=>$input['join_date'],'salary'=>$input['salary'],'paid_leave_days'=>$input['paid_leave_days'],'status'=>$input['status'],'address'=>$input['address']?:null,'notes'=>$input['notes']?:null];
        if($id===null){ $s=$pdo->prepare('INSERT INTO employees (employee_code,name,phone,email,role_position,join_date,salary,paid_leave_days,status,address,notes) VALUES (:code,:name,:phone,:email,:role_position,:join_date,:salary,:paid_leave_days,:status,:address,:notes)'); $s->execute(array_merge(['code'=>'TMP-'.bin2hex(random_bytes(4))],$values)); $id=(int)$pdo->lastInsertId(); $s=$pdo->prepare('UPDATE employees SET employee_code=:code WHERE id=:id'); $s->execute(['code'=>'EMP-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),'id'=>$id]); }
        else { $s=$pdo->prepare('UPDATE employees SET name=:name,phone=:phone,email=:email,role_position=:role_position,join_date=:join_date,salary=:salary,paid_leave_days=:paid_leave_days,status=:status,address=:address,notes=:notes WHERE id=:id'); $s->execute(array_merge(['id'=>$id],$values)); }
        $pdo->commit();
    } catch(Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack(); throw $exception; }
}

function handle_employee_request(string $section): void
{
    ensure_employee_tables(); $errors=[]; $employeeId=(int)($_GET['id']??$_POST['employee_id']??0); $employee=$employeeId>0?employee_find($employeeId):null;
    if($section==='employees-advances' && (current_user()['role'] ?? '') === 'cashier'){
        redirect('index.php?page=admin&section=cashier-advance-requests');
    }
    if(in_array($section,['employees-view','employees-edit','employees-delete'],true)&&!$employee){ http_response_code(404); exit('Employee not found.'); }
    if($section==='employees-attendance'){
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['attendance_action'] ?? '') === 'checkout') {
            verify_csrf();
            $checkoutId = (int) ($_POST['checkout_id'] ?? 0);
            $record = attendance_find($checkoutId);
            $automaticCheckout = ($_POST['checkout_mode'] ?? '') === 'now';
            $checkout = $automaticCheckout
                ? (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('H:i:s')
                : trim((string) ($_POST['check_out'] ?? ''));
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $checkout)) $checkout .= ':00';
            if (!$record || !$record['check_in'] || $record['check_out'] || !in_array($record['attendance_status'], ['present', 'half_day'], true)) {
                flash('error', 'Check out is available only for a checked-in employee who has not checked out yet.');
            } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $checkout) || $checkout < $record['check_in']) {
                flash('error', 'Check-out time cannot be earlier than check-in time.');
                redirect('index.php?page=admin&section=employees-attendance' . ($automaticCheckout ? '' : '&attendance_checkout_id=' . $checkoutId));
            } else {
                $s = database()->prepare("UPDATE employee_attendance SET check_out=:check_out WHERE id=:id AND check_out IS NULL AND check_in IS NOT NULL AND check_in<=:checkout_limit AND attendance_status IN ('present','half_day')");
                $s->execute(['check_out' => $checkout, 'id' => $checkoutId, 'checkout_limit' => $checkout]);
                flash($s->rowCount() ? 'success' : 'error', $s->rowCount() ? 'Check-out time saved successfully.' : 'This attendance record has changed. Please check it again.');
            }
            redirect('index.php?page=admin&section=employees-attendance');
        }
        $isCashier = (current_user()['role'] ?? '') === 'cashier';
        if($isCashier && (isset($_GET['attendance_edit_id']) || (int)($_POST['attendance_id'] ?? 0) > 0 || (($_POST['attendance_action'] ?? '') === 'delete'))){
            flash('error','Cashiers can view and mark attendance, but cannot edit or delete attendance records.');
            redirect('index.php?page=admin&section=employees-attendance');
        }
        $attendanceId=(int)($_GET['attendance_edit_id']??$_GET['attendance_checkout_id']??$_GET['attendance_view_id']??$_POST['attendance_id']??0); $attendanceEdit=$attendanceId>0?attendance_find($attendanceId):null; if($attendanceEdit)$employeeId=(int)$attendanceEdit['employee_id'];
        if($attendanceId>0&&!$attendanceEdit){http_response_code(404);exit('Attendance record not found.');}
        if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['attendance_action']??'')==='delete'){
            verify_csrf(); $s=database()->prepare('DELETE FROM employee_attendance WHERE id=:id'); $s->execute(['id'=>(int)$_POST['attendance_id']]); flash('success','Attendance record deleted.'); redirect('index.php?page=admin&section=employees-attendance');
        }
        if($_SERVER['REQUEST_METHOD']==='POST'){
            verify_csrf();
            $attendanceDate=trim((string)($_POST['attendance_date']??''));
            $attendanceStatus=$_POST['attendance_status']??'present';
            $date=DateTime::createFromFormat('Y-m-d',$attendanceDate);
            if(!$employee||!$date||$date->format('Y-m-d')!==$attendanceDate)$errors[]='Select a valid employee and attendance date.';
            if(!in_array($attendanceStatus,['present','absent','leave','half_day'],true))$errors[]='Select a valid attendance status.';
            if(!$errors){
                if($attendanceId>0){$s=database()->prepare('UPDATE employee_attendance SET employee_id=:employee_id,attendance_date=:attendance_date,attendance_status=:status,check_in=:check_in,check_out=:check_out,notes=:notes WHERE id=:id');$s->execute(['id'=>$attendanceId,'employee_id'=>$employeeId,'attendance_date'=>$attendanceDate,'status'=>$attendanceStatus,'check_in'=>trim((string)($_POST['check_in']??''))?:null,'check_out'=>trim((string)($_POST['check_out']??''))?:null,'notes'=>trim((string)($_POST['attendance_notes']??''))?:null]);}
                else {$s=database()->prepare('INSERT INTO employee_attendance (employee_id,attendance_date,attendance_status,check_in,check_out,notes) VALUES (:employee_id,:attendance_date,:status,:check_in,:check_out,:notes) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),check_in=VALUES(check_in),check_out=VALUES(check_out),notes=VALUES(notes)');$s->execute(['employee_id'=>$employeeId,'attendance_date'=>$attendanceDate,'status'=>$attendanceStatus,'check_in'=>trim((string)($_POST['check_in']??''))?:null,'check_out'=>trim((string)($_POST['check_out']??''))?:null,'notes'=>trim((string)($_POST['attendance_notes']??''))?:null]);}
                flash('success','Attendance marked successfully.');
                redirect('index.php?page=admin&section=employees-attendance');
            }
        }
        $attendanceFilters=attendance_filters(); $attendanceRows=attendance_rows($attendanceFilters); $attendanceModalRecord=$attendanceEdit; $attendanceModalType=isset($_GET['attendance_checkout_id'])?'checkout':(isset($_GET['attendance_view_id'])?'view':(isset($_GET['attendance_edit_id'])?'edit':'')); $title='Attendance'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employees.php'; if($attendanceModalType!=='')require __DIR__.'/../views/attendance-modal-'.$attendanceModalType.'.php'; require __DIR__.'/../includes/footer.php'; return;
    }
    if($section==='employees-view' || ($section==='employees-edit' && $_SERVER['REQUEST_METHOD'] !== 'POST')){
        $selectedEmployee = $employee;
        $input = $selectedEmployee;
        $title = 'Employee List';
        $sectionForHeader = 'employees';
        require __DIR__.'/../includes/header.php';
        $section = 'employees';
        require __DIR__.'/../views/employees.php';
        $employee = $selectedEmployee;
        $input = $selectedEmployee;
        require __DIR__.'/../views/'.($employeeId && $sectionForHeader === 'employees' && $_GET['section'] === 'employees-edit' ? 'employee-edit-modal.php' : 'employee-view.php');
        require __DIR__.'/../includes/footer.php';
        return;
    }
    if($section==='employees-delete'&&$_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); $s=database()->prepare("UPDATE employees SET status='inactive' WHERE id=:id"); $s->execute(['id'=>$employeeId]); flash('success','Employee marked as inactive. Existing records were preserved.'); redirect('index.php?page=admin&section=employees'); }
    $input=employee_input();
    if(in_array($section,['employees-add','employees-edit'],true)&&$_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); $errors=employee_validate($input); if(!$errors){ try{ employee_save($input,$section==='employees-edit'?$employeeId:null); flash('success',$section==='employees-edit'?'Employee updated successfully.':'Employee added successfully.'); redirect('index.php?page=admin&section=employees'); }catch(Throwable $exception){$errors[]='Employee could not be saved.';} } }
    elseif($section==='employees-edit'&&$employee){ $input=$employee; }
    $options=employee_options();
    $salaryActionId=(int)($_GET['salary_edit_id']??$_GET['salary_view_id']??$_POST['salary_id']??0); $salaryActionRecord=$salaryActionId>0?salary_find($salaryActionId):null;
    if($section==='employees-salary'&&$salaryActionId>0&&!$salaryActionRecord){http_response_code(404);exit('Salary record not found.');}
    if($section==='employees-salary'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['salary_action']??'')==='delete'){
        verify_csrf(); if(!$salaryActionRecord||$salaryActionRecord['status']==='paid')$errors[]='Paid salary records cannot be deleted.'; else {$delete=database()->prepare('DELETE FROM employee_salaries WHERE id=:id AND status=\'pending\'');$delete->execute(['id'=>$salaryActionId]);flash('success','Salary record deleted.');redirect('index.php?page=admin&section=employees-salary');}
    }
    if($section==='employees-salary'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['salary_action']??'')==='update'){
        verify_csrf(); $editMonth=trim((string)($_POST['salary_month']??'')); $editDate=DateTime::createFromFormat('Y-m-d',$editMonth.'-01'); $editBasic=(float)($_POST['basic_salary']??0);$editAllowances=(float)($_POST['allowances']??0);$editDeductions=(float)($_POST['deductions']??0);$editAdvance=(float)($_POST['advance_deduction']??0);$editLoan=(float)($_POST['loan_deduction']??0);
        if(!$salaryActionRecord)$errors[]='Salary record not found.';
        if(!$editDate||$editDate->format('Y-m-d')!==$editMonth.'-01')$errors[]='Select a valid salary month.';
        if($editBasic<0||$editAllowances<0||$editDeductions<0||$editAdvance<0||$editLoan<0)$errors[]='Salary amounts cannot be negative.';
        if(!$errors){$newNet=round($editBasic+$editAllowances-$editDeductions,2);$update=database()->prepare('UPDATE employee_salaries SET salary_month=:month,basic_salary=:basic,allowances=:allow,deductions=:ded,advance_deduction=:advance_deduction,loan_deduction=:loan_deduction,net_salary=:net,amount_paid=CASE WHEN status=\'paid\' THEN :amount_paid ELSE amount_paid END WHERE id=:id');$update->execute(['month'=>$editMonth.'-01','basic'=>$editBasic,'allow'=>$editAllowances,'ded'=>$editDeductions,'advance_deduction'=>$editAdvance,'loan_deduction'=>$editLoan,'net'=>$newNet,'amount_paid'=>$newNet,'id'=>$salaryActionId]);flash('success','Salary record updated successfully.');redirect('index.php?page=admin&section=employees-salary');}
    }
    if($section==='employees-salary'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['salary_action']??'')==='pay'){
        verify_csrf(); $salaryId=(int)($_POST['salary_id']??0); $pdo=database();
        $salaryStatement=$pdo->prepare('SELECT * FROM employee_salaries WHERE id=:id LIMIT 1'); $salaryStatement->execute(['id'=>$salaryId]); $salaryRecord=$salaryStatement->fetch();
        if(!$salaryRecord)$errors[]='Salary record not found.';
        elseif($salaryRecord['status']==='paid')$errors[]='This salary has already been paid.';
        if(!$errors){
            $receiptNo='SAL-'.date('Ym',strtotime($salaryRecord['salary_month'])).'-'.str_pad((string)$salaryRecord['employee_id'],4,'0',STR_PAD_LEFT).'-'.strtoupper(bin2hex(random_bytes(2)));
            try{$pdo->beginTransaction();$lockedAdvance=$pdo->prepare("SELECT id,balance FROM employee_advances WHERE employee_id=:employee_id AND status='unsettled' AND balance>0 ORDER BY advance_date,id FOR UPDATE");$lockedAdvance->execute(['employee_id'=>$salaryRecord['employee_id']]);$advanceRows=$lockedAdvance->fetchAll();$lockedLoan=$pdo->prepare("SELECT id,balance FROM employee_loans WHERE employee_id=:employee_id AND status='active' AND balance>0 ORDER BY loan_date,id FOR UPDATE");$lockedLoan->execute(['employee_id'=>$salaryRecord['employee_id']]);$loanRows=$lockedLoan->fetchAll();$advanceRemaining=(float)$salaryRecord['advance_deduction'];$loanRemaining=(float)$salaryRecord['loan_deduction'];foreach($advanceRows as $advanceRow){if($advanceRemaining<=0)break;$deduct=min($advanceRemaining,(float)$advanceRow['balance']);$newBalance=round((float)$advanceRow['balance']-$deduct,2);$updateAdvance=$pdo->prepare("UPDATE employee_advances SET balance=:balance,status=:status WHERE id=:id");$updateAdvance->execute(['balance'=>$newBalance,'status'=>$newBalance<=0?'settled':'unsettled','id'=>$advanceRow['id']]);$advanceRemaining=round($advanceRemaining-$deduct,2);}foreach($loanRows as $loanRow){if($loanRemaining<=0)break;$deduct=min($loanRemaining,(float)$loanRow['balance']);$newBalance=round((float)$loanRow['balance']-$deduct,2);$updateLoan=$pdo->prepare("UPDATE employee_loans SET balance=:balance,status=:status WHERE id=:id");$updateLoan->execute(['balance'=>$newBalance,'status'=>$newBalance<=0?'settled':'active','id'=>$loanRow['id']]);$loanRemaining=round($loanRemaining-$deduct,2);}if($advanceRemaining>0.009||$loanRemaining>0.009)throw new RuntimeException('Outstanding balance changed before payment.');$payStatement=$pdo->prepare("UPDATE employee_salaries SET status='paid',amount_paid=:amount_paid,receipt_no=:receipt_no,paid_at=:paid_at WHERE id=:id AND status='pending'");$payStatement->execute(['amount_paid'=>$salaryRecord['net_salary'],'receipt_no'=>$receiptNo,'paid_at'=>date('Y-m-d H:i:s'),'id'=>$salaryId]);if($payStatement->rowCount()!==1)throw new RuntimeException('Salary status changed before payment.');$pdo->commit();flash('success','Salary marked as paid and receipt created.');redirect('index.php?page=admin&section=employees-salary&receipt_id='.$salaryId);}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$errors[]='Salary could not be marked as paid.';}
        }
    }
    if($section==='employees-salary'&&$_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf(); $salaryMonth=trim((string)($_POST['salary_month']??'')); $monthDate=DateTime::createFromFormat('Y-m-d',$salaryMonth.'-01'); $basic=(float)($_POST['basic_salary']??0); $allowances=(float)($_POST['allowances']??0); $manualDeductions=(float)($_POST['deductions']??0); $advanceDeduction=(float)($_POST['advance_deduction']??0); $loanDeduction=(float)($_POST['loan_deduction']??0); $salaryEmployee=employee_find($employeeId); $financials=$employeeId>0?salary_financial_summary($employeeId):['advance_total'=>0,'loan_total'=>0];
        if(!$salaryEmployee)$errors[]='Select a valid employee.';
        if(!$monthDate||$monthDate->format('Y-m-d')!==$salaryMonth.'-01')$errors[]='Select a valid salary month.';
        if($basic<0||$allowances<0||$manualDeductions<0||$advanceDeduction<0||$loanDeduction<0)$errors[]='Salary amounts cannot be negative.';
        if($advanceDeduction>(float)$financials['advance_total'])$errors[]='Advance deduction cannot exceed the outstanding advance balance.';
        if($loanDeduction>(float)$financials['loan_total'])$errors[]='Loan deduction cannot exceed the outstanding loan balance.';
        if(!$errors){$summary=salary_attendance_summary($employeeId,$salaryMonth);$paidLeave=min((float)$salaryEmployee['paid_leave_days'],(float)$summary['leave_days']);$unpaidLeave=max(0,(float)$summary['leave_days']-$paidLeave);$unpaidDays=(float)$summary['absent_days']+$unpaidLeave+((float)$summary['half_days']*0.5);$salaryDaysAfterPaidLeave=max(1,(float)$summary['calendar_days']-$paidLeave);$perDay=$basic/$salaryDaysAfterPaidLeave;$attendanceDeduction=round($perDay*$unpaidDays,2);$totalDeductions=$manualDeductions+$attendanceDeduction+$advanceDeduction+$loanDeduction;$net=round($basic+$allowances-$totalDeductions,2);$status='pending';$receiptNo=null;$notes='Month days: '.$summary['calendar_days'].' | Paid leave: '.$paidLeave.' | No-pay leave days: '.$unpaidDays.' | No-pay leave deduction: '.number_format($attendanceDeduction,2).' | Working days: '.$summary['working_days'].' | Present: '.$summary['present_days'].' | Unpaid leave: '.$unpaidLeave.' | Absent: '.$summary['absent_days'].' | Half days: '.$summary['half_days'].' | Advance deduction: '.number_format($advanceDeduction,2).' | Loan deduction: '.number_format($loanDeduction,2).' | Outstanding advance: '.number_format((float)$financials['advance_total'],2).' | Outstanding loan: '.number_format((float)$financials['loan_total'],2);$pdo=database();$pdo->beginTransaction();try{$s=$pdo->prepare('INSERT INTO employee_salaries (employee_id,salary_month,basic_salary,allowances,deductions,advance_deduction,loan_deduction,unpaid_leave_days,attendance_deduction,net_salary,amount_paid,receipt_no,status,paid_at,notes) VALUES (:employee_id,:month,:basic,:allow,:ded,:advance_deduction,:loan_deduction,:unpaid_leave_days,:attendance_deduction,:net,0,NULL,:status,NULL,:notes)');$s->execute(['employee_id'=>$employeeId,'month'=>$salaryMonth.'-01','basic'=>$basic,'allow'=>$allowances,'ded'=>$totalDeductions,'advance_deduction'=>$advanceDeduction,'loan_deduction'=>$loanDeduction,'unpaid_leave_days'=>$unpaidDays,'attendance_deduction'=>$attendanceDeduction,'net'=>$net,'status'=>$status,'notes'=>$notes]);$receiptId=(int)$pdo->lastInsertId();$pdo->commit();flash('success','Salary calculated and saved as pending.');redirect('index.php?page=admin&section=employees-salary&receipt_id='.$receiptId.'&receipt_mode=calculation');}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$errors[]='Salary could not be calculated or saved. The month may already have a salary record.';}}
    }
    $loanId=(int)($_GET['loan_edit_id']??$_POST['loan_id']??0); $loanEdit=$loanId>0?employee_loan_find($loanId):null;
    if($section==='employees-loans'&&$loanId>0&&!$loanEdit){http_response_code(404);exit('Loan record not found.');}
    if($section==='employees-loans'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['loan_action']??'')==='delete'){
        verify_csrf(); $s=database()->prepare('DELETE FROM employee_loans WHERE id=:id'); $s->execute(['id'=>(int)$_POST['loan_id']]); flash('success','Loan record deleted.'); redirect('index.php?page=admin&section=employees-loans');
    }
    if($section==='employees-loans'&&$_SERVER['REQUEST_METHOD']==='POST'&&$loanId>0){
        verify_csrf(); $loanAmount=(float)($_POST['amount']??0); $loanDate=trim((string)($_POST['loan_date']??'')); $loanDateObject=DateTime::createFromFormat('Y-m-d',$loanDate);
        if(!in_array($employeeId,array_map('intval',array_column($options,'id')),true))$errors[]='Select a valid employee.';
        if($loanAmount<=0)$errors[]='Loan amount must be greater than zero.';
        if(!$loanDateObject||$loanDateObject->format('Y-m-d')!==$loanDate)$errors[]='Select a valid loan date.';
        if(!$errors){$s=database()->prepare('UPDATE employee_loans SET employee_id=:employee_id,loan_date=:loan_date,amount=:amount,balance=:balance,reason=:reason,status=:status WHERE id=:id');$s->execute(['id'=>$loanId,'employee_id'=>$employeeId,'loan_date'=>$loanDate,'amount'=>$loanAmount,'balance'=>$loanAmount,'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>in_array($_POST['status']??'active',['active','settled'],true)?$_POST['status']:'active']);flash('success','Loan record updated successfully.');redirect('index.php?page=admin&section=employees-loans');}
    }
    $advanceId=(int)($_GET['advance_edit_id']??$_GET['advance_view_id']??$_POST['advance_id']??0); $advanceEdit=$advanceId>0?employee_advance_find($advanceId):null;
    if($section==='employees-advances'&&current_user()['role']==='cashier'&&(isset($_GET['advance_edit_id'])||($_POST['advance_action']??'')==='delete'||($_SERVER['REQUEST_METHOD']==='POST'&&$advanceId>0))){flash('error','Cashiers cannot edit or delete employee advances.');redirect('index.php?page=admin&section=employees-advances');}
    if($section==='employees-advances'&&$advanceId>0&&!$advanceEdit){http_response_code(404);exit('Advance record not found.');}
    if($section==='employees-advances'&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['advance_action']??'')==='delete'){
        verify_csrf(); $s=database()->prepare('DELETE FROM employee_advances WHERE id=:id'); $s->execute(['id'=>(int)$_POST['advance_id']]); flash('success','Advance record deleted.'); redirect('index.php?page=admin&section=employees-advances');
    }
    if($section==='employees-advances'&&$_SERVER['REQUEST_METHOD']==='POST'&&$advanceId>0){
        verify_csrf(); $advanceAmount=(float)($_POST['amount']??0); $advanceDate=trim((string)($_POST['advance_date']??'')); $advanceDateObject=DateTime::createFromFormat('Y-m-d',$advanceDate);
        if(!in_array($employeeId,array_map('intval',array_column($options,'id')),true))$errors[]='Select a valid employee.';
        if($advanceAmount<=0)$errors[]='Advance amount must be greater than zero.';
        if(!$advanceDateObject||$advanceDateObject->format('Y-m-d')!==$advanceDate)$errors[]='Select a valid advance date.';
        if(!$errors){$s=database()->prepare('UPDATE employee_advances SET employee_id=:employee_id,advance_date=:advance_date,amount=:amount,reason=:reason,status=:status WHERE id=:id');$s->execute(['id'=>$advanceId,'employee_id'=>$employeeId,'advance_date'=>$advanceDate,'amount'=>$advanceAmount,'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>in_array($_POST['status']??'unsettled',['unsettled','settled'],true)?$_POST['status']:'unsettled']);flash('success','Advance record updated successfully.');redirect('index.php?page=admin&section=employees-advances');}
    }
    if(in_array($section,['employees-advances','employees-loans'],true)&&$_SERVER['REQUEST_METHOD']==='POST'&&!isset($_POST['loan_id'])&&!isset($_POST['advance_id'])){
        verify_csrf();
        if(!in_array($employeeId,array_map('intval',array_column($options,'id')),true))$errors[]='Select a valid employee.';
        else { $pdo=database(); $pdo->beginTransaction(); try { if($section==='employees-salary'){ $basic=(float)($_POST['basic_salary']??0);$allowances=(float)($_POST['allowances']??0);$deductions=(float)($_POST['deductions']??0);$s=$pdo->prepare('INSERT INTO employee_salaries (employee_id,salary_month,basic_salary,allowances,deductions,net_salary,status,notes) VALUES (:employee_id,:month,:basic,:allow,:ded,:net,:status,:notes)');$s->execute(['employee_id'=>$employeeId,'month'=>$_POST['salary_month'],'basic'=>$basic,'allow'=>$allowances,'ded'=>$deductions,'net'=>$basic+$allowances-$deductions,'status'=>$_POST['status']??'pending','notes'=>trim((string)($_POST['notes']??''))?:null]); } elseif($section==='employees-advances'){ $amount=(float)$_POST['amount'];$s=$pdo->prepare('INSERT INTO employee_advances (employee_id,advance_date,amount,balance,reason,status) VALUES (:employee_id,:date,:amount,:balance,:reason,:status)');$s->execute(['employee_id'=>$employeeId,'date'=>$_POST['advance_date'],'amount'=>$amount,'balance'=>$amount,'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>$_POST['status']??'unsettled']); } else { $amount=(float)$_POST['amount'];$s=$pdo->prepare('INSERT INTO employee_loans (employee_id,loan_date,amount,balance,reason,status) VALUES (:employee_id,:date,:amount,:balance,:reason,:status)');$s->execute(['employee_id'=>$employeeId,'date'=>$_POST['loan_date'],'amount'=>$amount,'balance'=>$amount,'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>$_POST['status']??'active']); } $pdo->commit(); flash('success','Employee record saved successfully.'); redirect('index.php?page=admin&section='.$section); } catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$errors[]='The record could not be saved.';} }
    }
    if($section==='employees-salary'){
        $salaryPreviewMonth=preg_match('/^\d{4}-\d{2}$/',(string)($_POST['salary_month']??''))?$_POST['salary_month']:date('Y-m'); $salaryAttendancePreview=[]; foreach($options as $previewOption){$previewSummary=salary_attendance_summary((int)$previewOption['id'],$salaryPreviewMonth);$previewPaidLeave=min((float)$previewOption['paid_leave_days'],(float)$previewSummary['leave_days']);$previewUnpaidLeave=max(0,(float)$previewSummary['leave_days']-$previewPaidLeave);$previewUnpaidDays=(float)$previewSummary['absent_days']+$previewUnpaidLeave+((float)$previewSummary['half_days']*0.5);$previewPerDay=(float)$previewSummary['calendar_days']-$previewPaidLeave>0?(float)$previewOption['salary']/((float)$previewSummary['calendar_days']-$previewPaidLeave):0;$salaryAttendancePreview[(int)$previewOption['id']]=['unpaid_days'=>$previewUnpaidDays,'deduction'=>round($previewPerDay*$previewUnpaidDays,2)];}
        $salaryEmployeeFilter=(int)($_GET['salary_employee_id']??0); $salaryMonthFrom=preg_match('/^\d{4}-\d{2}$/',(string)($_GET['salary_month_from']??''))?$_GET['salary_month_from']:''; $salaryMonthTo=preg_match('/^\d{4}-\d{2}$/',(string)($_GET['salary_month_to']??''))?$_GET['salary_month_to']:''; $salaryStatusFilter=in_array($_GET['salary_status']??'', ['pending','paid'], true)?$_GET['salary_status']:''; $salaryWhere=[];$salaryParams=[];if($salaryEmployeeFilter>0){$salaryWhere[]='s.employee_id=:filter_employee';$salaryParams['filter_employee']=$salaryEmployeeFilter;}if($salaryMonthFrom!==''){$salaryWhere[]='s.salary_month>=:filter_month_from';$salaryParams['filter_month_from']=$salaryMonthFrom.'-01';}if($salaryMonthTo!==''){$salaryWhere[]='s.salary_month<=:filter_month_to';$salaryParams['filter_month_to']=$salaryMonthTo.'-01';}if($salaryStatusFilter!==''){$salaryWhere[]='s.status=:filter_status';$salaryParams['filter_status']=$salaryStatusFilter;}$salaryQuery='SELECT s.*,e.name employee_name,e.employee_code FROM employee_salaries s JOIN employees e ON e.id=s.employee_id'.($salaryWhere?' WHERE '.implode(' AND ',$salaryWhere):'').' ORDER BY s.salary_month DESC,s.id DESC';$salaryStatement=database()->prepare($salaryQuery);$salaryStatement->execute($salaryParams);$salaryRows=$salaryStatement->fetchAll(); $salaryEmployeeOptions=employee_all_options(); $salaryFinancials=[]; foreach($options as $option)$salaryFinancials[(int)$option['id']]=salary_financial_summary((int)$option['id']); $salaryModalOpen=(isset($_GET['salary_add'])&&$_GET['salary_add']==='1')||!empty($errors); $salaryEditModal=$salaryActionRecord!==null&&(isset($_GET['salary_edit_id'])||($_POST['salary_action']??'')==='update'); $salaryViewModal=$salaryActionRecord!==null&&isset($_GET['salary_view_id']); $salaryReceiptId=(int)($_GET['receipt_id']??0); $salaryReceipt=$salaryReceiptId>0?salary_find($salaryReceiptId):null; $salaryPayId=(int)($_GET['salary_pay_id']??0); $salaryPay=$salaryPayId>0?salary_find($salaryPayId):null; $title='Salary Management'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employee-salaries.php'; if($salaryModalOpen)require __DIR__.'/../views/employee-salary-modal.php'; if($salaryEditModal)require __DIR__.'/../views/employee-salary-edit-modal.php'; if($salaryViewModal)require __DIR__.'/../views/employee-salary-view-modal.php'; if($salaryPay)require __DIR__.'/../views/employee-salary-pay-modal.php'; if($salaryReceipt)require __DIR__.'/../views/employee-salary-receipt.php'; require __DIR__.'/../includes/footer.php'; return;
    }
    if($section==='employees-advances'){
        $advanceRows=database()->query('SELECT a.*,e.name employee_name,e.employee_code FROM employee_advances a JOIN employees e ON e.id=a.employee_id ORDER BY a.advance_date DESC,a.id DESC')->fetchAll();
        $advanceEditModal=$advanceEdit!==null; $advanceModalOpen=(isset($_GET['advance_add']) && $_GET['advance_add']==='1') || $advanceEditModal || !empty($errors); $advanceModalType=isset($_GET['advance_view_id'])?'view':($advanceEditModal?'edit':'add');
        $title='Staff Advances'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employee-advances.php'; if($advanceModalOpen)require __DIR__.'/../views/employee-advance-'.$advanceModalType.'-modal.php'; require __DIR__.'/../includes/footer.php'; return;
    }
    if($section==='employees-loans'){
        $loanRows=database()->query('SELECT l.*,e.name employee_name,e.employee_code FROM employee_loans l JOIN employees e ON e.id=l.employee_id ORDER BY l.loan_date DESC,l.id DESC')->fetchAll();
        $loanEditModal=$loanEdit!==null; $loanModalOpen=(isset($_GET['loan_add']) && $_GET['loan_add']==='1') || $loanEditModal || !empty($errors);
        $title='Staff Loans'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employee-loans.php'; if($loanModalOpen)require __DIR__.'/../views/'.($loanEditModal?'employee-loan-edit-modal.php':'employee-loan-modal.php'); require __DIR__.'/../includes/footer.php'; return;
    }
    $title=ucwords(str_replace('-',' ',$section)); $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employees.php'; require __DIR__.'/../includes/footer.php';
}
