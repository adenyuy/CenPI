<?php
use Ramsey\Uuid\Uuid;

class UserModel extends Model {
    public function register($username, $password, $email) {
        try {
            $this->db->beginTransaction();
    
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
            $sql = "INSERT INTO USERS (id_user, username, password, email, token_activation)
                                VALUES (:id_user, :username, :password, :email, :token_activation)";
    
            $id_user = Uuid::uuid7()->toString();
            $token_activation = Uuid::uuid7()->toString();
    
            $this->db->query($sql);
            $this->db->bind(':id_user', $id_user);
            $this->db->bind(':username', $username);
            $this->db->bind(':password', $hashedPassword);
            $this->db->bind(':token_activation', $token_activation);
            $this->db->bind(':email', $email);
    
            $this->db->execute();
            $this->db->commit();
    
            // Send verification email
            $this->sendVerificationEmail($email, $token_activation);
    
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Register Error: " . $e->getMessage());
            return false;
        }
    }

    private function sendVerificationEmail($email, $token) {
        $mailer = new Mailer();
        $subject = 'Account Verification';
        $verificationLink = BASE_URL . "/User/verify/" . $token;
        $body = "Please click the following link to verify your account: <a href='$verificationLink'>Verify Account</a>";
    
        return $mailer->sendMail($email, $subject, $body);
    }

    public function login($username, $password) {
        try {
            $sql = "SELECT * FROM USERS WHERE username = :username";
            $this->db->query($sql);
            $this->db->bind(':username', $username);

            $user = $this->db->single();

            if ($user && password_verify($password, $user['PASSWORD'])) {
                return $user;
            }

            return false;
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return false;
        }
    }

    public function verifyUser ($token) {
        try {
            $sql = "SELECT id_user, active FROM USERS WHERE token_activation = :token";
            $this->db->query($sql);
            $this->db->bind(':token', $token);
    
            $user = $this->db->single();
    
            if ($user) { 
                if ($user['ACTIVE'] == 0) {
                    $sql = "UPDATE USERS SET active = 1 WHERE id_user = :id_user";
                    $this->db->query($sql);
                    $this->db->bind(':id_user', $user['ID_USER']);
                    $this->db->execute();
    
                    return "Account activated successfully.";
                } else if ($user['ACTIVE'] == 1) {
                    return "Account already activated.";
                }
            }
    
            return "Token not valid.";
        } catch (Exception $e) {
            error_log("Verification Error: " . $e->getMessage());
            return "An error occurred during verification.";
        }
    }

    public function getUserById($userId) {
        $sql = "SELECT * FROM USERS WHERE id_user = :id_user";
        $this->db->query($sql);
        $this->db->bind(':id_user', $userId);

        return $this->db->single();
    }

    public function updateUserProfile($userId, $hashedNewPassword) {
        $this->db->beginTransaction();

        try {
            $sql = "UPDATE USERS 
                    SET password = :password 
                    WHERE id_user = :id_user";

            $this->db->query($sql);
            $this->db->bind(':password', $hashedNewPassword);
            $this->db->bind(':id_user', $userId);

            $this->db->execute();
            $this->db->commit();

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function verifyPassword($userId, $password) {
        $sql = "SELECT password FROM USERS WHERE id_user = :id_user";
        $this->db->query($sql);
        $this->db->bind(':id_user', $userId);
        
        $user = $this->db->single();
        return password_verify($password, $user['PASSWORD']);
    }

    public function findUserByUsernameOrEmail($username, $email) {
        try {
            $sql = "SELECT * FROM USERS WHERE username = :username OR email = :email";
            $this->db->query($sql);
            
            $this->db->bind(':username', $username);
            $this->db->bind(':email', $email);

            return $this->db->resultSet();
        } catch (Exception $e) {
            error_log("Find User By Username or Email Error: " . $e->getMessage());
            return false;
        }
    }
}
