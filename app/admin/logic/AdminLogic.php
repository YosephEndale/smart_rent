<?php
namespace App\Admin\Logic;

use App\Admin\Data\AdminData;

class AdminLogic {
    private $adminData;

    public function __construct($conn) {
        if (!$conn instanceof \PDO) {
            throw new \InvalidArgumentException('Invalid PDO connection provided');
        }
        $this->adminData = new AdminData($conn);
    }

    public function authenticate($name, $password) {
        $admin = $this->adminData->getAdminByName($name);
        if ($admin && password_verify($password, $admin['password'])) {
            return $admin['id'];
        }
        return false;
    }

    public function register($name, $password, $confirm_password) {
        if ($password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match!'];
        }
        $existing_admin = $this->adminData->getAdminByName($name);
        if ($existing_admin) {
            return ['success' => false, 'message' => 'Username already taken!'];
        }
        $id = uniqid('admin_', true);
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        if ($this->adminData->insertAdmin($id, $name, $hashed_pass)) {
            return ['success' => true, 'admin_id' => $id];
        }
        return ['success' => false, 'message' => 'Registration failed!'];
    }

    public function updateProfile($admin_id, $name, $old_pass, $new_pass, $c_pass): array {
        $result = ['success' => [], 'errors' => []];
        $admin = $this->adminData->getAdminById($admin_id);

        if (!$admin) {
            $result['errors'][] = 'Admin not found!';
            return $result;
        }

        if ($name) {
            $verify_name = $this->adminData->getAdmins($name);
            if (count(array_filter($verify_name, fn($a) => $a['id'] !== $admin_id)) > 0) {
                $result['errors'][] = 'Username already taken!';
            } else {
                $this->adminData->updateAdminName($admin_id, $name);
                $result['success'][] = 'Username updated!';
            }
        }

        if ($old_pass) {
            if (!password_verify($old_pass, $admin['password'])) {
                $result['errors'][] = 'Old password not matched!';
            } elseif ($new_pass !== $c_pass) {
                $result['errors'][] = 'New password not matched!';
            } elseif (empty($new_pass)) {
                $result['errors'][] = 'Please enter a new password!';
            } else {
                $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $this->adminData->updateAdminPassword($admin_id, $hashed_new_pass);
                $result['success'][] = 'Password updated!';
            }
        }

        return $result;
    }

    public function deleteAdmin($admin_id): array {
        $current_admin_id = $_SESSION['admin_id'] ?? null;
        if ($admin_id === $current_admin_id) {
            return ['success' => false, 'message' => 'Cannot delete your own account!'];
        }
        if ($this->adminData->deleteAdmin($admin_id)) {
            return ['success' => true, 'message' => 'Admin deleted!'];
        }
        return ['success' => false, 'message' => 'Admin already deleted or not found!'];
    }
}