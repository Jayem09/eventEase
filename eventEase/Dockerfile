# Use the official PHP image from Docker Hub
FROM php:7.4-apache

# Install dependencies (e.g., extensions, composer)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy the contents of the current directory into the container
COPY . .

# Expose port 80 to be accessible from outside
EXPOSE 80

CMD ["apache2-foreground"]
