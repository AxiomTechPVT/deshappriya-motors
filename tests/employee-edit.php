<?php
declare(strict_types=1);
require __DIR__ . '/../includes/employees.php';
function database(): PDO {
    static $pdo;
    return $pdo ??= new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return 'test'; }
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = database();
$pdo->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY AUTOINCREMENT,employee_code,name,phone,email,role_position,join_date,salary,paid_leave_days,status,address,notes)');
foreach (['First'=>'inactive','Second'=>'active','Third'=>'active'] as $name=>$status) {
    employee_save(['name'=>$name,'phone'=>'0711234567','email'=>'','role_position'=>'Mechanic','join_date'=>'2026-09-15','salary'=>60000,'paid_leave_days'=>2,'status'=>$status,'address'=>'Address','notes'=>'Notes']);
}
foreach ([2,1] as $selectedId) {
    $employee = employee_find($selectedId);
    $input = $employee;
    $section = 'employees'; $errors = [];
    ob_start();
    require __DIR__ . '/../views/employees.php';
    require __DIR__ . '/../views/employee-edit-modal.php';
    $html = ob_get_clean();
    check((int)$employee['id'] === $selectedId, 'Employee list rendering must not overwrite the selected record.');
    $modal = substr($html, strpos($html, 'aria-labelledby="employee-edit-modal-title"'));
    check(str_contains($modal,'section=employees-edit&amp;id='.$selectedId), 'Modal must post to the selected employee ID.');
    check(str_contains($modal,'value="'.$input['status'].'" selected'), 'Modal must retain active/inactive status.');
    check(str_contains($modal,'name="name" value="'.$input['name'].'"'), 'Modal must load the selected name.');
}
$before = employee_find(1);
$edited = employee_find(2); $edited['name'] = 'Second edited';
employee_save($edited,2);
check(employee_find(1) === $before, 'Editing employee 2 must not change employee 1.');
check(employee_find(2)['name'] === 'Second edited' && employee_find(2)['status'] === 'active', 'Edited name and active status must be saved on employee 2.');
echo "PASS: selected employee ID, modal values/status and isolated employee update.\n";
