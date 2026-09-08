<?php

declare(strict_types=1);

function user_management_input(array $source = []): array
{
    return ['name'=>trim((string)($source['name']??'')),'username'=>strtolower(trim((string)($source['username']??''))),'email'=>strtolower(trim((string)($source['email']??''))),'mobile'=>trim((string)($source['mobile']??'')),'status'=>($source['status']??'active')==='inactive'?'inactive':'active','password'=>(string)($source['password']??''),'password_confirmation'=>(string)($source['password_confirmation']??'')];
}

function user_management_filters(): array
{
    $status = $_GET['status'] ?? '';
    return ['search'=>trim((string)($_GET['search']??'')),'status'=>in_array($status,['active','inactive'],true)?$status:''];
}

function cashier_by_id(int $id): ?array
{
    $statement=database()->prepare('SELECT id,name,username,email,mobile,role,status,last_login_at,created_at,updated_at FROM users WHERE id=:id AND role="cashier" LIMIT 1'); $statement->execute(['id'=>$id]); return $statement->fetch()?:null;
}

function cashier_list(array $filters): array
{
    $where=['role="cashier"']; $params=[];
    if($filters['search']!==''){$where[]='(name LIKE :search OR username LIKE :search OR email LIKE :search OR mobile LIKE :search)';$params['search']='%'.$filters['search'].'%';}
    if($filters['status']!==''){$where[]='status=:status';$params['status']=$filters['status'];}
    $statement=database()->prepare('SELECT id,name,username,email,mobile,role,status,last_login_at,created_at FROM users WHERE '.implode(' AND ',$where).' ORDER BY id DESC');$statement->execute($params);return $statement->fetchAll();
}

function cashier_counts(): array
{
    return database()->query('SELECT COUNT(*) AS total,SUM(status="active") AS active,SUM(status="inactive") AS inactive FROM users WHERE role="cashier"')->fetch()?:['total'=>0,'active'=>0,'inactive'=>0];
}

function validate_cashier_input(array $input,bool $passwordRequired=true,int $ignoreId=0): array
{
    $errors=[];
    if($input['name']==='')$errors[]='Full name is required.';
    if(!preg_match('/^[a-z][a-z0-9._-]{2,79}$/i',$input['username']))$errors[]='Username must be 3-80 characters and use only letters, numbers, dots, underscores, or hyphens.';
    if($input['email']!==''&&!filter_var($input['email'],FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
    if($input['mobile']!==''&&!preg_match('/^[0-9+() .-]{7,40}$/',$input['mobile']))$errors[]='Enter a valid mobile number.';
    if($passwordRequired&&strlen($input['password'])<8)$errors[]='Password must be at least 8 characters.';
    if($input['password']!==''&&$input['password']!==$input['password_confirmation'])$errors[]='Password confirmation does not match.';
    $duplicate=database()->prepare('SELECT username,email FROM users WHERE (LOWER(username)=:username OR (:email<>"" AND LOWER(email)=:email)) AND id<>:id LIMIT 1');$duplicate->execute(['username'=>strtolower($input['username']),'email'=>strtolower($input['email']),'id'=>$ignoreId]);
    if($record=$duplicate->fetch()){if(strcasecmp((string)$record['username'],$input['username'])===0)$errors[]='Username is already in use.';elseif($input['email']!=='')$errors[]='Email is already in use.';}
    return $errors;
}

function handle_user_management_request(string $section): void
{
    ensure_user_access_columns();$filters=user_management_filters();$id=max(0,(int)($_GET['id']??$_POST['id']??0));$modal=in_array($_GET['modal']??'', ['add','view','edit','password'],true)?$_GET['modal']:'';$errors=[];$input=user_management_input($_POST);
    if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$action=$_POST['action']??'';$pdo=database();
        if($action==='add'){$errors=validate_cashier_input($input,true);$modal='add';if(!$errors){try{$s=$pdo->prepare('INSERT INTO users(name,username,email,mobile,role,status,password) VALUES(:name,:username,:email,:mobile,"cashier",:status,:password)');$s->execute(['name'=>$input['name'],'username'=>$input['username'],'email'=>$input['email']?:null,'mobile'=>$input['mobile']?:null,'status'=>$input['status'],'password'=>password_hash($input['password'],PASSWORD_DEFAULT)]);flash('success','Cashier account created successfully.');redirect('index.php?page=admin&section=users-cashiers');}catch(PDOException $e){$errors[]='The cashier could not be saved. Check the username and email.';}}}
        elseif($action==='edit'){$cashier=cashier_by_id($id);$errors=$cashier?validate_cashier_input($input,false,$id):['Cashier account not found.'];$modal='edit';if(!$errors){try{$s=$pdo->prepare('UPDATE users SET name=:name,username=:username,email=:email,mobile=:mobile,status=:status WHERE id=:id AND role="cashier"');$s->execute(['name'=>$input['name'],'username'=>$input['username'],'email'=>$input['email']?:null,'mobile'=>$input['mobile']?:null,'status'=>$input['status'],'id'=>$id]);flash('success','Cashier account updated successfully.');redirect('index.php?page=admin&section=users-cashiers');}catch(PDOException $e){$errors[]='The cashier could not be updated. Check the username and email.';}}}
        elseif($action==='password'){$cashier=cashier_by_id($id);$errors=$cashier?validate_cashier_input($input,true,$id):['Cashier account not found.'];$modal='password';if(!$errors){$s=$pdo->prepare('UPDATE users SET password=:password WHERE id=:id AND role="cashier"');$s->execute(['password'=>password_hash($input['password'],PASSWORD_DEFAULT),'id'=>$id]);flash('success','Cashier password updated successfully.');redirect('index.php?page=admin&section=users-cashiers');}}
        elseif($action==='toggle'){$cashier=cashier_by_id($id);if(!$cashier)flash('error','Cashier account not found.');else{$newStatus=$cashier['status']==='active'?'inactive':'active';$s=$pdo->prepare('UPDATE users SET status=:status WHERE id=:id AND role="cashier"');$s->execute(['status'=>$newStatus,'id'=>$id]);flash('success',$newStatus==='active'?'Cashier account activated.':'Cashier account deactivated.');}redirect('index.php?page=admin&section=users-cashiers');}
    }
    $selected=$id>0?cashier_by_id($id):null;if(!$input['name']&&in_array($modal,['edit','view'],true)&&$selected)$input=user_management_input($selected);$counts=cashier_counts();$rows=cashier_list($filters);$title='User Management';require __DIR__.'/../includes/header.php';require __DIR__.'/../views/users.php';require __DIR__.'/../includes/footer.php';
}
