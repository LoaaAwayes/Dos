Implementation Details:
  • Programming Language: PHP 
  • Framework: Lumen 
  • Database: SQLite 
  • Containerization: Docker
Tools Required:
  • Git– for cloning the project repository.
  • Docker – to build and run containerized services.
  • Docker Compose– to manage multi-container Docker applications.

Project Structure:
  • client_service: Front-end service.
  • catalog_service: Catalog microservice.
  • catalog2_service: Secondary catalog microservice.
  • order_service: Order processing microservice.
  • order2_service: Secondary order processing microservice.
Getting Started:
Run the Code Without Docker:
run the services manually using PHP's built-in server, follow the instructions below.
Start Each Service Manually:
Open five terminals, then run:
# Client Service
php -S localhost:9000 -t client/public
# Catalog Service
php -S localhost:9001 -t catalog/public
# Catalog2 Service
php -S localhost:9002 -t catalog2/public
# Order Service
php -S localhost:9003 -t order/public
# Order2 Service
php -S localhost:9004 -t order2/public
Access in Browser or Postman
Like this :
Method: GET
URL:  
http://localhost:9000/client/item/1
Method: POST
URL: 
http://localhost:9000/client/purchase/1
Method: PUT
URL:
http://localhost:9000/client/book/3?price=35.0&quantity=40.0&title=Xen and the Art of Surviving Undergraduate School

Run the Code With Docker:
Build and Start the Containers
docker-compose up –build
This command builds and starts all defined services.
Access the Services
  • Client Service: http://localhost:9000
  • Catalog Service: http://localhost:9001
  • Catalog2 Service: http://localhost:9002
  • Order Service: http://localhost:9003
  • Order2 Service: http://localhost:9004







