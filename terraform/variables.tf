variable "region" {
  description = "AWS region"
  type        = string
  default     = "eu-north-1" # Stockholm (as requested)
}

variable "name" {
  description = "Resource name prefix"
  type        = string
  default     = "tracker-test"
}

variable "instance_type" {
  description = "EC2 type"
  type        = string
  default     = "t3.micro"
}

variable "ssh_cidr" {
  description = "CIDR allowed to SSH (use your IP/32!)"
  type        = string
  default     = "0.0.0.0/0"
}

variable "public_key" {
  description = "Your SSH public key contents (e.g., file(\"~/.ssh/id_rsa.pub\"))"
  type        = string
  default     = ""
}

variable "key_name" {
  description = "Existing AWS key pair name (used when public_key is empty)"
  type        = string
  default     = "products-key"
}

variable "ssh_private_key_path" {
  description = "Path to your local SSH private key for provisioning (optional). e.g. ~/.ssh/products-key.pem"
  type        = string
  default     = "~/.ssh/products-key.pem"
}

variable "public_subnet_id" {
  description = "Optional: use an existing public subnet ID instead of creating one"
  type        = string
  default     = ""
}

variable "repo_url" {
  description = "Optional: HTTPS Git repo URL to clone on the instance (e.g., https://github.com/youruser/tracker.git). If empty, no clone will be performed."
  type        = string
  default     = ""
}
