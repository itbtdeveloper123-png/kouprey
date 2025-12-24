-- Migration to add image columns to about table
-- Run this if you already have the database set up

USE kouprey_db;

-- Add hero_image and person_image columns to about table
ALTER TABLE about
ADD COLUMN hero_image VARCHAR(500) AFTER content,
ADD COLUMN person_image VARCHAR(500) AFTER hero_image;