terraform {
  required_version = ">= 1.6.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

# Optional: create a key pair from your local public key (if provided)
resource "aws_key_pair" "this" {
  count      = var.public_key != "" ? 1 : 0
  key_name   = "${var.name}-key"
  public_key = var.public_key
}

# Security group: 22 for SSH (restrict!), 80 for HTTP
resource "aws_security_group" "this" {
  name        = "${var.name}-sg"
  description = "Allow SSH and HTTP"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.ssh_cidr]
  }

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    ipv6_cidr_blocks = ["::/0"]
  }

  egress {
    description = "All outbound"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
    ipv6_cidr_blocks = ["::/0"]
  }
}

# Use default VPC + a public subnet
data "aws_vpc" "default" {
  default = true
}
data "aws_subnets" "public" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

# AMI: Amazon Linux 2023 in the chosen region
data "aws_ami" "al2023" {
  most_recent = true
  owners      = ["137112412989"] # Amazon
  filter {
    name   = "name"
    values = ["al2023-ami-*-x86_64"]
  }
}

# User data script to install nginx and expose /tracker/public/
data "template_file" "user_data" {
  template = file("${path.module}/user_data.sh")
  vars = {
    repo_url            = var.repo_url
    db_name             = var.db_name
    db_user             = var.db_user
    db_password         = var.db_password
    maxmind_license_key = var.maxmind_license_key
  }
}

resource "aws_instance" "this" {
  ami                         = data.aws_ami.al2023.id
  instance_type               = var.instance_type
  subnet_id                   = var.public_subnet_id != "" ? var.public_subnet_id : element(data.aws_subnets.public.ids, 0)
  associate_public_ip_address = true
  vpc_security_group_ids      = [aws_security_group.this.id]
  key_name                    = var.public_key != "" ? aws_key_pair.this[0].key_name : var.key_name

  user_data = data.template_file.user_data.rendered

  tags = {
    Name = var.name
  }
}
