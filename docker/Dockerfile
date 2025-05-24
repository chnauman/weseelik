# Start from the Sail PHP 8.4 base image
FROM laravelsail/php84-composer

# Install system libraries for grpc
RUN apt-get update \
    && apt-get install -y libgrpc++-dev libgrpc-dev

# Install grpc PHP extension via pecl
RUN pecl install grpc \
    && docker-php-ext-enable grpc
