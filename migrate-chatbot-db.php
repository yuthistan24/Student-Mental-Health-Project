<?php
/**
 * Database Migration: Add student_chatbot_conversations table
 * 
 * This script adds the new conversation history table to track
 * all student-chatbot interactions for smarter personalization.
 * 
 * Run this once to update your database schema.
 */

require_once __DIR__ . '/includes/db.php';

try {
    $conn = getDbConnection($GLOBALS['dbConfig']);
    
    $sql = "CREATE TABLE IF NOT EXISTS student_chatbot_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        user_message TEXT NOT NULL,
        bot_reply TEXT NOT NULL,
        message_type ENUM('question', 'statement', 'feedback') DEFAULT 'question',
        topic_category VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX idx_student_chatbot_created (student_id, created_at),
        INDEX idx_topic_category (topic_category)
    )";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'success' => true,
            'message' => 'Database migration completed successfully. The student_chatbot_conversations table has been created.',
        ]);
    } else {
        throw new Exception('Failed to create table: ' . $conn->error);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
?>
