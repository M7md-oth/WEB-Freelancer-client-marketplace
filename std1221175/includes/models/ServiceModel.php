<?php

class ServiceModel {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getFeaturedServices($limit = 4) {
        $sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                FROM services s 
                JOIN users u ON s.freelancer_id = u.user_id 
                WHERE s.featured_status = 'Yes' AND s.status = 'Active' 
                ORDER BY s.created_date DESC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getAllServices() {
        $sql = "SELECT s.service_id, s.title, s.category, s.subcategory, s.price, s.status, 
                       s.created_date, s.image_1, s.featured_status,
                       u.first_name, u.last_name
                FROM services s
                JOIN users u ON s.freelancer_id = u.user_id
                ORDER BY s.created_date DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getServiceById($serviceId) {
        $sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                FROM services s 
                JOIN users u ON s.freelancer_id = u.user_id 
                WHERE s.service_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $serviceId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function getServicesByCategory($category, $limit = null) {
        $sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                FROM services s 
                JOIN users u ON s.freelancer_id = u.user_id 
                WHERE s.category = :category AND s.status = 'Active' 
                ORDER BY s.created_date DESC";
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':category', $category);
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function searchServices($searchTerm, $limit = null) {
        $sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo 
                FROM services s 
                JOIN users u ON s.freelancer_id = u.user_id 
                WHERE (s.title LIKE :search OR s.description LIKE :search2) 
                AND s.status = 'Active'
                ORDER BY s.created_date DESC";
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        $stmt = $this->pdo->prepare($sql);
        $searchPattern = "%" . $searchTerm . "%";
        $stmt->bindValue(':search', $searchPattern);
        $stmt->bindValue(':search2', $searchPattern);
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getServicesWithFilters($filters = [], $sort = 'newest', $page = 1, $perPage = 12) {
        $category = $filters['category'] ?? '';
        $search = trim($filters['search'] ?? '');
        $freelancerId = $filters['freelancer_id'] ?? '';
        
        $whereConditions = ["s.status = 'Active'"];
        $params = [];
        
        if (!empty($category)) {
            $whereConditions[] = "s.category = :category";
            $params[":category"] = $category;
        }
        
        if (!empty($freelancerId) && preg_match('/^[0-9]+$/', $freelancerId)) {
            $whereConditions[] = "s.freelancer_id = :freelancer_id";
            $params[":freelancer_id"] = $freelancerId;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(LOWER(s.title) LIKE LOWER(:search) OR LOWER(s.description) LIKE LOWER(:search2))";
            $params[":search"] = "%" . $search . "%";
            $params[":search2"] = "%" . $search . "%";
        }
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $orderBy = match($sort) {
            'oldest' => 's.created_date ASC',
            'price_low' => 's.price ASC',
            'price_high' => 's.price DESC',
            default => 's.created_date DESC'
        };
        
        $countSql = "SELECT COUNT(*) FROM services s WHERE $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT s.*, u.first_name, u.last_name, u.profile_photo
                FROM services s
                JOIN users u ON s.freelancer_id = u.user_id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT $perPage OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $services = $stmt->fetchAll();
        
        return [
            'services' => $services,
            'total' => (int)$total
        ];
    }
    
    public function getCategories() {
        $sql = "SELECT category_name FROM categories ORDER BY category_name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

