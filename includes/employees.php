<?php
declare(strict_types=1);

function ensure_employee_tables(): void
{
    static $ready = false;
    if ($ready) return;
    database()->exec("CREATE TABLE IF NOT EXISTS employees (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_code VARCHAR(20) NOT NULL, name VARCHAR(160) NOT NULL, phone VARCHAR(40) NOT NULL, email VARCHAR(190) NULL, role_position VARCHAR(120) NOT NULL, join_date DATE NOT NULL, salary DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', address TEXT NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employees_code_unique (employee_code), KEY employees_status_index (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_salaries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, salary_month DATE NOT NULL, basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0, allowances DECIMAL(12,2) NOT NULL DEFAULT 0, deductions DECIMAL(12,2) NOT NULL DEFAULT 0, net_salary DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('pending','paid') NOT NULL DEFAULT 'pending', paid_at DATETIME NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employee_salary_month_unique (employee_id, salary_month), CONSTRAINT employee_salaries_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_advances (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, advance_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, reason VARCHAR(255) NULL, status ENUM('unsettled','settled') NOT NULL DEFAULT 'unsettled', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), CONSTRAINT employee_advances_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_loans (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, loan_date DATE NOT NULL, amount DECIMAL(12,2) NOT NULL, installment DECIMAL(12,2) NOT NULL DEFAULT 0, balance DECIMAL(12,2) NOT NULL DEFAULT 0, reason VARCHAR(255) NULL, status ENUM('active','settled') NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), CONSTRAINT employee_loans_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    database()->exec("CREATE TABLE IF NOT EXISTS employee_attendance (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, employee_id BIGINT UNSIGNED NOT NULL, attendance_date DATE NOT NULL, attendance_status ENUM('present','absent','leave','half_day') NOT NULL DEFAULT 'present', check_in TIME NULL, check_out TIME NULL, notes VARCHAR(255) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY employee_attendance_day_unique (employee_id, attendance_date), KEY employee_attendance_date_index (attendance_date), KEY employee_attendance_status_index (attendance_status), CONSTRAINT employee_attendance_employee_fk FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function employee_input(): array
{
    $status = $_POST['status'] ?? 'active';
    return ['name'=>trim((string)($_POST['name']??'')),'phone'=>trim((string)($_POST['phone']??'')),'email'=>trim((string)($_POST['email']??'')),'role_position'=>trim((string)($_POST['role_position']??'')),'join_date'=>trim((string)($_POST['join_date']??date('Y-m-d'))),'salary'=>(float)($_POST['salary']??0),'status'=>in_array($status,['active','inactive'],true)?$status:'active','address'=>trim((string)($_POST['address']??'')),'notes'=>trim((string)($_POST['notes']??''))];
}
function employee_list(): array { return database()->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll(); }
function employee_options(): array { return database()->query("SELECT id,name,employee_code FROM employees WHERE status='active' ORDER BY name")->fetchAll(); }
function employee_all_options(): array { return database()->query('SELECT id,name,employee_code FROM employees ORDER BY name')->fetchAll(); }
function employee_find(int $id): ?array { $s=database()->prepare('SELECT * FROM employees WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_validate(array $input): array { $e=[]; if($input['name']==='')$e[]='Employee name is required.'; if($input['phone']==='')$e[]='Phone number is required.'; if($input['role_position']==='')$e[]='Role / position is required.'; $d=DateTime::createFromFormat('Y-m-d',$input['join_date']); if(!$d||$d->format('Y-m-d')!==$input['join_date'])$e[]='Join date is required.'; if($input['salary']<0)$e[]='Salary cannot be negative.'; if($input['email']!==''&&!filter_var($input['email'],FILTER_VALIDATE_EMAIL))$e[]='Enter a valid email address.'; return $e; }
function attendance_filters(): array { return ['employee_id'=>(int)($_GET['attendance_employee_id']??0),'status'=>in_array($_GET['attendance_status']??'', ['present','absent','leave','half_day'], true)?$_GET['attendance_status']:'','date_from'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_from']??''))?$_GET['date_from']:'','date_to'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_to']??''))?$_GET['date_to']:'']; }
function attendance_rows(array $filters): array { $where=[];$params=[]; if($filters['employee_id']>0){$where[]='a.employee_id=:employee_id';$params['employee_id']=$filters['employee_id'];} if($filters['status']!==''){$where[]='a.attendance_status=:status';$params['status']=$filters['status'];} if($filters['date_from']!==''){$where[]='a.attendance_date>=:date_from';$params['date_from']=$filters['date_from'];} if($filters['date_to']!==''){$where[]='a.attendance_date<=:date_to';$params['date_to']=$filters['date_to'];} $sql='SELECT a.*,e.name employee_name,e.employee_code FROM employee_attendance a JOIN employees e ON e.id=a.employee_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY a.attendance_date DESC,a.id DESC';$s=database()->prepare($sql);$s->execute($params);return $s->fetchAll(); }
function attendance_find(int $id): ?array { $s=database()->prepare('SELECT * FROM employee_attendance WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_loan_find(int $id): ?array { $s=database()->prepare('SELECT l.*,e.name employee_name,e.employee_code FROM employee_loans l JOIN employees e ON e.id=l.employee_id WHERE l.id=:id LIMIT 1'); $s->execute(['id'=>$id]); $row=$s->fetch(); return $row?:null; }
function employee_save(array $input, ?int $id=null): void
{
    $pdo=database(); $pdo->beginTransaction();
    try {
        $values=['name'=>$input['name'],'phone'=>$input['phone'],'email'=>$input['email']?:null,'role_position'=>$input['role_position'],'join_date'=>$input['join_date'],'salary'=>$input['salary'],'status'=>$input['status'],'address'=>$input['address']?:null,'notes'=>$input['notes']?:null];
        if($id===null){ $s=$pdo->prepare('INSERT INTO employees (employee_code,name,phone,email,role_position,join_date,salary,status,address,notes) VALUES (:code,:name,:phone,:email,:role_position,:join_date,:salary,:status,:address,:notes)'); $s->execute(array_merge(['code'=>'TMP-'.bin2hex(random_bytes(4))],$values)); $id=(int)$pdo->lastInsertId(); $s=$pdo->prepare('UPDATE employees SET employee_code=:code WHERE id=:id'); $s->execute(['code'=>'EMP-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),'id'=>$id]); }
        else { $s=$pdo->prepare('UPDATE employees SET name=:name,phone=:phone,email=:email,role_position=:role_position,join_date=:join_date,salary=:salary,status=:status,address=:address,notes=:notes WHERE id=:id'); $s->execute(array_merge(['id'=>$id],$values)); }
        $pdo->commit();
    } catch(Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack(); throw $exception; }
}

function handle_employee_request(string $section): void
{
    ensure_employee_tables(); $errors=[]; $employeeId=(int)($_GET['id']??$_POST['employee_id']??0); $employee=$employeeId>0?employee_find($employeeId):null;
    if(in_array($section,['employees-view','employees-edit','employees-delete'],true)&&!$employee){ http_response_code(404); exit('Employee not found.'); }
    if($section==='employees-attendance'){
        $attendanceId=(int)($_GET['attendance_edit_id']??$_GET['attendance_view_id']??$_POST['attendance_id']??0); $attendanceEdit=$attendanceId>0?attendance_find($attendanceId):null; if($attendanceEdit)$employeeId=(int)$attendanceEdit['employee_id'];
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
        $attendanceFilters=attendance_filters(); $attendanceRows=attendance_rows($attendanceFilters); $attendanceModalRecord=$attendanceEdit; $attendanceModalType=isset($_GET['attendance_view_id'])?'view':(isset($_GET['attendance_edit_id'])?'edit':''); $title='Attendance'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employees.php'; if($attendanceModalType!=='')require __DIR__.'/../views/attendance-modal-'.$attendanceModalType.'.php'; require __DIR__.'/../includes/footer.php'; return;
    }
    if($section==='employees-view' || ($section==='employees-edit' && $_SERVER['REQUEST_METHOD'] !== 'POST')){
        $input = $employee;
        $title = 'Employee List';
        $sectionForHeader = 'employees';
        require __DIR__.'/../includes/header.php';
        $section = 'employees';
        require __DIR__.'/../views/employees.php';
        require __DIR__.'/../views/'.($employeeId && $sectionForHeader === 'employees' && $_GET['section'] === 'employees-edit' ? 'employee-edit-modal.php' : 'employee-view.php');
        require __DIR__.'/../includes/footer.php';
        return;
    }
    if($section==='employees-delete'&&$_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); $s=database()->prepare("UPDATE employees SET status='inactive' WHERE id=:id"); $s->execute(['id'=>$employeeId]); flash('success','Employee marked as inactive. Existing records were preserved.'); redirect('index.php?page=admin&section=employees'); }
    $input=employee_input();
    if(in_array($section,['employees-add','employees-edit'],true)&&$_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); $errors=employee_validate($input); if(!$errors){ try{ employee_save($input,$section==='employees-edit'?$employeeId:null); flash('success',$section==='employees-edit'?'Employee updated successfully.':'Employee added successfully.'); redirect('index.php?page=admin&section=employees'); }catch(Throwable $exception){$errors[]='Employee could not be saved.';} } }
    elseif($section==='employees-edit'&&$employee){ $input=$employee; }
    $options=employee_options();
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
    if(in_array($section,['employees-salary','employees-advances','employees-loans'],true)&&$_SERVER['REQUEST_METHOD']==='POST'&&!isset($_POST['loan_id'])){
        verify_csrf();
        if(!in_array($employeeId,array_map('intval',array_column($options,'id')),true))$errors[]='Select a valid employee.';
        else { $pdo=database(); $pdo->beginTransaction(); try { if($section==='employees-salary'){ $basic=(float)($_POST['basic_salary']??0);$allowances=(float)($_POST['allowances']??0);$deductions=(float)($_POST['deductions']??0);$s=$pdo->prepare('INSERT INTO employee_salaries (employee_id,salary_month,basic_salary,allowances,deductions,net_salary,status,notes) VALUES (:employee_id,:month,:basic,:allow,:ded,:net,:status,:notes)');$s->execute(['employee_id'=>$employeeId,'month'=>$_POST['salary_month'],'basic'=>$basic,'allow'=>$allowances,'ded'=>$deductions,'net'=>$basic+$allowances-$deductions,'status'=>$_POST['status']??'pending','notes'=>trim((string)($_POST['notes']??''))?:null]); } elseif($section==='employees-advances'){ $s=$pdo->prepare('INSERT INTO employee_advances (employee_id,advance_date,amount,reason,status) VALUES (:employee_id,:date,:amount,:reason,:status)');$s->execute(['employee_id'=>$employeeId,'date'=>$_POST['advance_date'],'amount'=>(float)$_POST['amount'],'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>$_POST['status']??'unsettled']); } else { $amount=(float)$_POST['amount'];$s=$pdo->prepare('INSERT INTO employee_loans (employee_id,loan_date,amount,balance,reason,status) VALUES (:employee_id,:date,:amount,:balance,:reason,:status)');$s->execute(['employee_id'=>$employeeId,'date'=>$_POST['loan_date'],'amount'=>$amount,'balance'=>$amount,'reason'=>trim((string)($_POST['reason']??''))?:null,'status'=>$_POST['status']??'active']); } $pdo->commit(); flash('success','Employee record saved successfully.'); redirect('index.php?page=admin&section='.$section); } catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$errors[]='The record could not be saved.';} }
    }
    if($section==='employees-loans'){
        $loanRows=database()->query('SELECT l.*,e.name employee_name,e.employee_code FROM employee_loans l JOIN employees e ON e.id=l.employee_id ORDER BY l.loan_date DESC,l.id DESC')->fetchAll();
        $loanEditModal=$loanEdit!==null; $loanModalOpen=(isset($_GET['loan_add']) && $_GET['loan_add']==='1') || $loanEditModal || !empty($errors);
        $title='Staff Loans'; $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employee-loans.php'; if($loanModalOpen)require __DIR__.'/../views/'.($loanEditModal?'employee-loan-edit-modal.php':'employee-loan-modal.php'); require __DIR__.'/../includes/footer.php'; return;
    }
    $title=ucwords(str_replace('-',' ',$section)); $sectionForHeader=$section; require __DIR__.'/../includes/header.php'; require __DIR__.'/../views/employees.php'; require __DIR__.'/../includes/footer.php';
}
