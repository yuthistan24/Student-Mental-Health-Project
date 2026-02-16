-- Migration: Add Student Chatbot Conversation History Table
-- Purpose: Track all student-chatbot interactions for intelligent personalization
-- Date: 2026-02-16

CREATE TABLE IF NOT EXISTS student_chatbot_conversations (
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
);

-- Useful queries for monitoring and analysis:

-- Get conversation history for a specific student (most recent first)
-- SELECT user_message, bot_reply, created_at 
-- FROM student_chatbot_conversations 
-- WHERE student_id = 5 
-- ORDER BY created_at DESC LIMIT 20;

-- Find most discussed topics across all students
-- SELECT topic_category, COUNT(*) as frequency 
-- FROM student_chatbot_conversations 
-- GROUP BY topic_category 
-- ORDER BY frequency DESC;

-- Get students mentioning stress or anxiety
-- SELECT DISTINCT student_id 
-- FROM student_chatbot_conversations 
-- WHERE user_message LIKE '%stress%' OR user_message LIKE '%anxious%' OR user_message LIKE '%pressure%';

-- Show conversation frequency by student
-- SELECT student_id, COUNT(*) as total_conversations, MAX(created_at) as last_chat
-- FROM student_chatbot_conversations 
-- GROUP BY student_id 
-- ORDER BY total_conversations DESC;
