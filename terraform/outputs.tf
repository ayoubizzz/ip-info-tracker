output "public_ip" {
  value       = aws_instance.this.public_ip
  description = "Public IP address of the EC2 instance"
}

output "public_dns" {
  value       = aws_instance.this.public_dns
  description = "Public DNS name of the EC2 instance"
}

output "application_url" {
  value       = "http://${aws_instance.this.public_ip}/"
  description = "URL to access the visitor tracker application"
}

output "ssh_command" {
  value       = "ssh -i ~/.ssh/products-key.pem ec2-user@${aws_instance.this.public_ip}"
  description = "SSH command to connect to the instance"
}

output "database_check" {
  value       = "mysql -u tracker_user -p'[YOUR_PASSWORD]' tracker_db -e \"SELECT COUNT(*) FROM visitor_logs;\""
  description = "Command to check visitor logs count (run on EC2)"
}
