DATE=`date +%Y-%m-%d`
ARCH = x86
BASEIMAGE=ubuntu:bionic
PHUSION_VERSION=0.11
SHELL := /bin/bash

print-%  : ; @echo $* = $($*)

all:
	$(MAKE) purge
	$(MAKE) prepare
	$(MAKE) build
	$(MAKE) docs
	$(MAKE) push

purge:
	docker system prune -af

prepare:
	git rev-parse --short HEAD > marshall/marshall_version
	date +%Y-%m-%d\ %H:%M:%S > marshall/marshall_build_date
	hostname > marshall/marshall_build_host

cleanup:
	-docker rmi $(docker image ls | grep gone/php | sed 's/ \+ /\t/g' | cut -d$'\t' -f2 | grep -v "<none>" | xargs -n1 echo "gone/php:" | sed 's/ //g')
	-docker rmi $(docker image ls | grep gone/node | sed 's/ \+ /\t/g' | cut -d$'\t' -f2 | grep -v "<none>" | xargs -n1 echo "gone/node:" | sed 's/ //g')

build-base: prepare build-marshall

build-marshall:
	docker build --pull -t gone/marshall:latest -f ./marshall/Dockerfile ./marshall
	docker tag gone/marshall:latest gone/marshall:$(DATE)
	docker tag gone/marshall:latest gone/marshall:$(ARCH)
	docker tag gone/marshall:latest gone/marshall:$(ARCH)-$(DATE)

build-php-core-5.6:
	docker build --pull -t gone/php:core-5.6 -f ./php-core/Dockerfile.php56 ./php-core
	docker tag gone/php:core-5.6 gone/php:core-5.6-$(ARCH)
	docker tag gone/php:core-5.6 gone/php:core-5.6-$(ARCH)-$(DATE)

build-php-core-7.0:
	docker build --pull -t gone/php:core-7.0 -f ./php-core/Dockerfile.php70 ./php-core
	docker tag gone/php:core-7.0 gone/php:core-7.0-$(ARCH)
	docker tag gone/php:core-7.0 gone/php:core-7.0-$(ARCH)-$(DATE)

build-php-core-7.1:
	docker build --pull -t gone/php:core-7.1 -f ./php-core/Dockerfile.php71 ./php-core
	docker tag gone/php:core-7.1 gone/php:core-7.1-$(ARCH)
	docker tag gone/php:core-7.1 gone/php:core-7.1-$(ARCH)-$(DATE)

build-php-core-7.2:
	docker build --pull -t gone/php:core-7.2 -f ./php-core/Dockerfile.php72 ./php-core
	docker tag gone/php:core-7.2 gone/php:core-7.2-$(ARCH)
	docker tag gone/php:core-7.2 gone/php:core-7.2-$(ARCH)-$(DATE)

build-php-core-7.3:
	docker build --pull -t gone/php:core-7.3 -f ./php-core/Dockerfile.php73 ./php-core
	docker tag gone/php:core-7.3 gone/php:core-7.3-$(ARCH)
	docker tag gone/php:core-7.3 gone/php:core-7.3-$(ARCH)-$(DATE)

tag-php-core:
	docker tag gone/php:core-7.3 gone/php:core-$(ARCH)
	docker tag gone/php:core-7.3 gone/php:core

build-php-core: build-php-core-5.6 build-php-core-7.0 build-php-core-7.1 build-php-core-7.2 build-php-core-7.3 tag-php-core

build-php-cli-5.6:
	sed 's|FROM .*|FROM gone/php:core-5.6|g' ./php+cli/Dockerfile > ./php+cli/Dockerfile.php56
	docker build --pull -t gone/php:cli-php5.6 -f ./php+cli/Dockerfile.php56 ./php+cli
	docker tag gone/php:cli-php5.6 gone/php:cli-php5.6-$(ARCH)
	docker tag gone/php:cli-php5.6 gone/php:cli-php5.6-$(ARCH)-$(DATE)
	rm ./php+cli/Dockerfile.php56

build-php-cli-7.0:
	sed 's|FROM .*|FROM gone/php:core-7.0|g' ./php+cli/Dockerfile > ./php+cli/Dockerfile.php70
	docker build --pull -t gone/php:cli-php7.0 -f ./php+cli/Dockerfile.php70 ./php+cli
	docker tag gone/php:cli-php7.0 gone/php:cli-php7.0-$(ARCH)
	docker tag gone/php:cli-php7.0 gone/php:cli-php7.0-$(ARCH)-$(DATE)
	rm ./php+cli/Dockerfile.php70

build-php-cli-7.1:
	sed 's|FROM .*|FROM gone/php:core-7.1|g' ./php+cli/Dockerfile > ./php+cli/Dockerfile.php71
	docker build --pull -t gone/php:cli-php7.1 -f ./php+cli/Dockerfile.php71 ./php+cli
	docker tag gone/php:cli-php7.1 gone/php:cli-php7.1-$(ARCH)
	docker tag gone/php:cli-php7.1 gone/php:cli-php7.1-$(ARCH)-$(DATE)
	rm ./php+cli/Dockerfile.php71

build-php-cli-7.2:
	sed 's|FROM .*|FROM gone/php:core-7.2|g' ./php+cli/Dockerfile > ./php+cli/Dockerfile.php72
	docker build --pull -t gone/php:cli-php7.2 -f ./php+cli/Dockerfile.php72 ./php+cli
	docker tag gone/php:cli-php7.2 gone/php:cli-php7.2-$(ARCH)
	docker tag gone/php:cli-php7.2 gone/php:cli-php7.2-$(ARCH)-$(DATE)
	rm ./php+cli/Dockerfile.php72

build-php-cli-7.3:
	sed 's|FROM .*|FROM gone/php:core-7.3|g' ./php+cli/Dockerfile > ./php+cli/Dockerfile.php73
	docker build --pull -t gone/php:cli-php7.3 -f ./php+cli/Dockerfile.php73 ./php+cli
	docker tag gone/php:cli-php7.3 gone/php:cli-php7.3-$(ARCH)
	docker tag gone/php:cli-php7.3 gone/php:cli-php7.3-$(ARCH)-$(DATE)
	rm ./php+cli/Dockerfile.php73


build-php-cli: build-php-cli-7.0 build-php-cli-7.1 build-php-cli-7.2 build-php-cli-7.3 tag-php-cli

build-php-apache-5.6:
	sed 's|FROM .*|FROM gone/php:core-5.6|g' ./php+apache/Dockerfile > ./php+apache/Dockerfile.php56
	sed -i 's/{{PHPVERSION}}/5.6/g' ./php+apache/Dockerfile.php56
	docker build --pull -t gone/php:apache-php5.6 -f ./php+apache/Dockerfile.php56 ./php+apache
	docker tag gone/php:apache-php5.6 gone/php:apache-php5.6-$(DATE)
	docker tag gone/php:apache-php5.6 gone/php:apache-php5.6-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php5.6 gone/php:apache-php5.6-$(ARCH)
	rm ./php+apache/Dockerfile.php56
	
build-php-apache-7.0:
	sed 's|FROM .*|FROM gone/php:core-7.0|g' ./php+apache/Dockerfile > ./php+apache/Dockerfile.php70
	sed -i 's/{{PHPVERSION}}/7.0/g' ./php+apache/Dockerfile.php70
	docker build --pull -t gone/php:apache-php7.0 -f ./php+apache/Dockerfile.php70 ./php+apache
	docker tag gone/php:apache-php7.0 gone/php:apache-php7.0-$(DATE)
	docker tag gone/php:apache-php7.0 gone/php:apache-php7.0-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php7.0 gone/php:apache-php7.0-$(ARCH)
	rm ./php+apache/Dockerfile.php70
	
build-php-apache-7.1:
	sed 's|FROM .*|FROM gone/php:core-7.1|g' ./php+apache/Dockerfile > ./php+apache/Dockerfile.php71
	sed -i 's/{{PHPVERSION}}/7.1/g' ./php+apache/Dockerfile.php71
	docker build --pull -t gone/php:apache-php7.1 -f ./php+apache/Dockerfile.php71 ./php+apache
	docker tag gone/php:apache-php7.1 gone/php:apache-php7.1-$(DATE)
	docker tag gone/php:apache-php7.1 gone/php:apache-php7.1-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php7.1 gone/php:apache-php7.1-$(ARCH)
	rm ./php+apache/Dockerfile.php71
	
build-php-apache-7.2:
	sed 's|FROM .*|FROM gone/php:core-7.2|g' ./php+apache/Dockerfile > ./php+apache/Dockerfile.php72
	sed -i 's/{{PHPVERSION}}/7.2/g' ./php+apache/Dockerfile.php72
	docker build --pull -t gone/php:apache-php7.2 -f ./php+apache/Dockerfile.php72 ./php+apache
	docker tag gone/php:apache-php7.2 gone/php:apache-php7.2-$(DATE)
	docker tag gone/php:apache-php7.2 gone/php:apache-php7.2-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php7.2 gone/php:apache-php7.2-$(ARCH)
	rm ./php+apache/Dockerfile.php72
	
build-php-apache-7.3:
	sed 's|FROM .*|FROM gone/php:core-7.3|g' ./php+apache/Dockerfile > ./php+apache/Dockerfile.php73
	sed -i 's/{{PHPVERSION}}/7.3/g' ./php+apache/Dockerfile.php73
	docker build --pull -t gone/php:apache-php7.3 -f ./php+apache/Dockerfile.php73 ./php+apache
	docker tag gone/php:apache-php7.3 gone/php:apache-php7.3-$(DATE)
	docker tag gone/php:apache-php7.3 gone/php:apache-php7.3-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php7.3 gone/php:apache-php7.3-$(ARCH)
	rm ./php+apache/Dockerfile.php73

tag-apache:
	docker run gone/php:apache-php7.0 php --version | head -n1 | cut -d ' ' -f2 | cut -d '+' -f1
	docker run gone/php:apache-php7.1 php --version | head -n1 | cut -d ' ' -f2 | cut -d '+' -f1
	docker run gone/php:apache-php7.2 php --version | head -n1 | cut -d ' ' -f2 | cut -d '+' -f1
	docker run gone/php:apache-php7.3 php --version | head -n1 | cut -d ' ' -f2 | cut -d '+' -f1
	docker tag gone/php:apache-php7.3 gone/php:apache-$(ARCH)-$(DATE)
	docker tag gone/php:apache-php7.3 gone/php:apache-$(ARCH)
	docker tag gone/php:apache-php7.3 gone/php:apache

test-php-apache-5.6:
	docker-compose -f test.yml -p apache56 up -d apache-php56-instance
	sleep 5;
	docker-compose -f test.yml -p apache56 run apache-php56-test | grep "Requests per second"
	docker-compose -f test.yml -p apache56 down -v;

test-php-apache-7.0:
	docker-compose -f test.yml -p apache70 up -d apache-php70-instance
	sleep 5;
	docker-compose -f test.yml -p apache70 run apache-php70-test | grep "Requests per second"
	docker-compose -f test.yml -p apache70 down -v;

test-php-apache-7.1:
	docker-compose -f test.yml -p apache71 up -d apache-php71-instance
	sleep 5;
	docker-compose -f test.yml -p apache71 run apache-php71-test | grep "Requests per second"
	docker-compose -f test.yml -p apache71 down -v;

test-php-apache-7.2:
	docker-compose -f test.yml -p apache72 up -d apache-php72-instance
	sleep 5;
	docker-compose -f test.yml -p apache72 run apache-php72-test | grep "Requests per second"
	docker-compose -f test.yml -p apache72 down -v;

test-php-apache-7.3:
	docker-compose -f test.yml -p apache73 up -d apache-php73-instance
	sleep 5;
	docker-compose -f test.yml -p apache73 run apache-php73-test | grep "Requests per second"
	docker-compose -f test.yml -p apache73 down -v;

build-php-apache: build-php-apache-5.6 build-php-apache-7.0 build-php-apache-7.1 build-php-apache-7.2 build-php-apache-7.3 tag-apache

build-php-nginx-5.6:
	sed 's|FROM .*|FROM gone/php:core-5.6|g' ./php+nginx/Dockerfile > ./php+nginx/Dockerfile.php56
	sed -i 's/{{PHPVERSION}}/5.6/g' ./php+nginx/Dockerfile.php56
	sed 's/{{PHPVERSION}}/5.6/g' ./php+nginx/configure-nginx.sh > ./php+nginx/configure-nginx.php5.6.sh
	docker build --pull -t gone/php:nginx-php5.6 -f ./php+nginx/Dockerfile.php56 ./php+nginx
	docker tag gone/php:nginx-php5.6 gone/php:nginx-php5.6-$(DATE)
	docker tag gone/php:nginx-php5.6 gone/php:nginx-php5.6-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php5.6 gone/php:nginx-php5.6-$(ARCH)
	rm ./php+nginx/Dockerfile.php56
	
build-php-nginx-7.0:
	sed 's|FROM .*|FROM gone/php:core-7.0|g' ./php+nginx/Dockerfile > ./php+nginx/Dockerfile.php70
	sed -i 's/{{PHPVERSION}}/7.0/g' ./php+nginx/Dockerfile.php70
	sed 's/{{PHPVERSION}}/7.0/g' ./php+nginx/configure-nginx.sh > ./php+nginx/configure-nginx.php7.0.sh
	docker build --pull -t gone/php:nginx-php7.0 -f ./php+nginx/Dockerfile.php70 ./php+nginx
	docker tag gone/php:nginx-php7.0 gone/php:nginx-php7.0-$(DATE)
	docker tag gone/php:nginx-php7.0 gone/php:nginx-php7.0-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php7.0 gone/php:nginx-php7.0-$(ARCH)
	rm ./php+nginx/Dockerfile.php70

build-php-nginx-7.1:
	sed 's|FROM .*|FROM gone/php:core-7.1|g' ./php+nginx/Dockerfile > ./php+nginx/Dockerfile.php71
	sed -i 's/{{PHPVERSION}}/7.1/g' ./php+nginx/Dockerfile.php71
	sed 's/{{PHPVERSION}}/7.1/g' ./php+nginx/configure-nginx.sh > ./php+nginx/configure-nginx.php7.1.sh
	docker build --pull -t gone/php:nginx-php7.1 -f ./php+nginx/Dockerfile.php71 ./php+nginx
	docker tag gone/php:nginx-php7.1 gone/php:nginx-php7.1-$(DATE)
	docker tag gone/php:nginx-php7.1 gone/php:nginx-php7.1-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php7.1 gone/php:nginx-php7.1-$(ARCH)
	rm ./php+nginx/Dockerfile.php71

build-php-nginx-7.2:
	sed 's|FROM .*|FROM gone/php:core-7.2|g' ./php+nginx/Dockerfile > ./php+nginx/Dockerfile.php72
	sed -i 's/{{PHPVERSION}}/7.2/g' ./php+nginx/Dockerfile.php72
	sed 's/{{PHPVERSION}}/7.2/g' ./php+nginx/configure-nginx.sh > ./php+nginx/configure-nginx.php7.2.sh
	docker build --pull -t gone/php:nginx-php7.2 -f ./php+nginx/Dockerfile.php72 ./php+nginx
	docker tag gone/php:nginx-php7.2 gone/php:nginx-php7.2-$(DATE)
	docker tag gone/php:nginx-php7.2 gone/php:nginx-php7.2-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php7.2 gone/php:nginx-php7.2-$(ARCH)
	rm ./php+nginx/Dockerfile.php72

build-php-nginx-7.3:
	sed 's|FROM .*|FROM gone/php:core-7.3|g' ./php+nginx/Dockerfile > ./php+nginx/Dockerfile.php73
	sed -i 's/{{PHPVERSION}}/7.3/g' ./php+nginx/Dockerfile.php73
	sed 's/{{PHPVERSION}}/7.3/g' ./php+nginx/configure-nginx.sh > ./php+nginx/configure-nginx.php7.3.sh
	docker build --pull -t gone/php:nginx-php7.3 -f ./php+nginx/Dockerfile.php73 ./php+nginx
	docker tag gone/php:nginx-php7.3 gone/php:nginx-php7.3-$(DATE)
	docker tag gone/php:nginx-php7.3 gone/php:nginx-php7.3-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php7.3 gone/php:nginx-php7.3-$(ARCH)
	rm ./php+nginx/Dockerfile.php73

tag-nginx:
	docker run gone/php:nginx-php5.6 php --version
	docker run gone/php:nginx-php7.0 php --version
	docker run gone/php:nginx-php7.1 php --version
	docker run gone/php:nginx-php7.2 php --version
	docker run gone/php:nginx-php7.3 php --version
	docker tag gone/php:nginx-php7.3 gone/php:nginx-$(DATE)
	docker tag gone/php:nginx-php7.3 gone/php:nginx-$(ARCH)-$(DATE)
	docker tag gone/php:nginx-php7.3 gone/php:nginx-$(ARCH)
	docker tag gone/php:nginx-php7.3 gone/php:nginx

test-php-nginx-5.6:
	docker-compose -f test.yml -p nginx56 up -d nginx-php56-instance
	sleep 5;
	docker-compose -f test.yml -p nginx56 run nginx-php56-test | grep "Requests per second"
	docker-compose -f test.yml -p nginx56 down -v;
	
test-php-nginx-7.0:
	docker-compose -f test.yml -p nginx70 up -d nginx-php70-instance
	sleep 5;
	docker-compose -f test.yml -p nginx70 run nginx-php70-test | grep "Requests per second"
	docker-compose -f test.yml -p nginx70 down -v;

test-php-nginx-7.1:
	docker-compose -f test.yml -p nginx71 up -d nginx-php71-instance
	sleep 5;
	docker-compose -f test.yml -p nginx71 run nginx-php71-test | grep "Requests per second"
	docker-compose -f test.yml -p nginx71 down -v;

test-php-nginx-7.2:
	docker-compose -f test.yml -p nginx72 up -d nginx-php72-instance
	sleep 5;
	docker-compose -f test.yml -p nginx72 run nginx-php72-test | grep "Requests per second"
	docker-compose -f test.yml -p nginx72 down -v;

test-php-nginx-7.3:
	docker-compose -f test.yml -p nginx73 up -d nginx-php73-instance
	sleep 5;
	docker-compose -f test.yml -p nginx73 run nginx-php73-test | grep "Requests per second"
	docker-compose -f test.yml -p nginx73 down -v;

build-php-nginx: build-php-nginx-5.6 build-php-nginx-7.0 build-php-nginx-7.1 build-php-nginx-7.2 build-php-nginx-7.3 tag-nginx

build-php-5.6: build-base build-php-core-5.6 build-php-cli-5.6 build-php-apache-5.6 build-php-nginx-5.6
build-php-7.0: build-base build-php-core-7.0 build-php-cli-7.0 build-php-apache-7.0 build-php-nginx-7.0
build-php-7.1: build-base build-php-core-7.1 build-php-cli-7.1 build-php-apache-7.1 build-php-nginx-7.1
build-php-7.2: build-base build-php-core-7.2 build-php-cli-7.2 build-php-apache-7.2 build-php-nginx-7.2
build-php-7.3: build-base build-php-core-7.3 build-php-cli-7.3 build-php-apache-7.3 build-php-nginx-7.3

build-node-8:
	cp ./nodejs/Dockerfile ./nodejs/Dockerfile.node8
	cp ./nodejs/Dockerfile.withBuildTools ./nodejs/Dockerfile.node8.withBuildTools
	sed -i 's/{{NODE_VERSION}}/8\.16\.0/g' ./nodejs/Dockerfile.node8
	sed -i 's/{{YARN_VERSION}}/1\.15\.2/g' ./nodejs/Dockerfile.node8
	sed -i 's/{{NODE_WITHOUT_BUILD_TOOLS}}/gone\/node\:8/g' ./nodejs/Dockerfile.node8.withBuildTools
	docker build --pull -t gone/node:8 -f ./nodejs/Dockerfile.node8 ./nodejs
	docker build --pull -t gone/node:8-gcc -f ./nodejs/Dockerfile.node8.withBuildTools ./nodejs
	rm ./nodejs/Dockerfile.node8 ./nodejs/Dockerfile.node8.withBuildTools

build-node-10:
	cp ./nodejs/Dockerfile ./nodejs/Dockerfile.node10
	cp ./nodejs/Dockerfile.withBuildTools ./nodejs/Dockerfile.node10.withBuildTools
	sed -i 's/{{NODE_VERSION}}/10\.16\.0/g' ./nodejs/Dockerfile.node10
	sed -i 's/{{YARN_VERSION}}/1\.16\.0/g' ./nodejs/Dockerfile.node10
	sed -i 's/{{NODE_WITHOUT_BUILD_TOOLS}}/gone\/node\:10/g' ./nodejs/Dockerfile.node10.withBuildTools
	docker build --pull -t gone/node:10 -f ./nodejs/Dockerfile.node10 ./nodejs
	docker build --pull -t gone/node:10-gcc -f ./nodejs/Dockerfile.node10.withBuildTools ./nodejs
	rm ./nodejs/Dockerfile.node10 ./nodejs/Dockerfile.node10.withBuildTools

build-node-11:
	cp ./nodejs/Dockerfile ./nodejs/Dockerfile.node11
	cp ./nodejs/Dockerfile.withBuildTools ./nodejs/Dockerfile.node11.withBuildTools
	sed -i 's/{{NODE_VERSION}}/11\.15\.0/g' ./nodejs/Dockerfile.node11
	sed -i 's/{{YARN_VERSION}}/1\.16\.0/g' ./nodejs/Dockerfile.node11
	sed -i 's/{{NODE_WITHOUT_BUILD_TOOLS}}/gone\/node\:11/g' ./nodejs/Dockerfile.node11.withBuildTools
	docker build --pull -t gone/node:11 -f ./nodejs/Dockerfile.node11 ./nodejs
	docker build --pull -t gone/node:11-gcc -f ./nodejs/Dockerfile.node11.withBuildTools ./nodejs
	rm ./nodejs/Dockerfile.node11 ./nodejs/Dockerfile.node11.withBuildTools

build-node-12:
	cp ./nodejs/Dockerfile ./nodejs/Dockerfile.node12
	cp ./nodejs/Dockerfile.withBuildTools ./nodejs/Dockerfile.node12.withBuildTools
	sed -i 's/{{NODE_VERSION}}/12\.3\.1/g' ./nodejs/Dockerfile.node12
	sed -i 's/{{YARN_VERSION}}/1\.16\.0/g' ./nodejs/Dockerfile.node12
	sed -i 's/{{NODE_WITHOUT_BUILD_TOOLS}}/gone\/node\:12/g' ./nodejs/Dockerfile.node12.withBuildTools
	docker build --pull -t gone/node:12 -f ./nodejs/Dockerfile.node12 ./nodejs
	docker build --pull -t gone/node:12-gcc -f ./nodejs/Dockerfile.node12.withBuildTools ./nodejs
	rm ./nodejs/Dockerfile.node12 ./nodejs/Dockerfile.node12.withBuildTools

build-node: build-node-8 build-node-10 build-node-11 build-node-12

tag-node: tag-node-8 tag-node-10 tag-node-11 tag-node-12

tag-node-8:
	docker tag gone/node:8     gone/node:8-$(DATE)
	docker tag gone/node:8     gone/node:8-$(ARCH)-$(DATE)
	docker tag gone/node:8 	   gone/node:8-$(ARCH)
	docker tag gone/node:8-gcc gone/node:8-gcc-$(DATE)
	docker tag gone/node:8-gcc gone/node:8-gcc-$(ARCH)-$(DATE)
	docker tag gone/node:8-gcc gone/node:8-gcc-$(ARCH)

tag-node-10:
	docker tag gone/node:10     gone/node:10-$(DATE)
	docker tag gone/node:10     gone/node:10-$(ARCH)-$(DATE)
	docker tag gone/node:10     gone/node:10-$(ARCH)
	docker tag gone/node:10-gcc gone/node:10-gcc-$(DATE)
	docker tag gone/node:10-gcc gone/node:10-gcc-$(ARCH)-$(DATE)
	docker tag gone/node:10-gcc gone/node:10-gcc-$(ARCH)

tag-node-11:
	docker tag gone/node:11     gone/node:11-$(DATE)
	docker tag gone/node:11     gone/node:11-$(ARCH)-$(DATE)
	docker tag gone/node:11     gone/node:11-$(ARCH)
	docker tag gone/node:11-gcc gone/node:11-gcc-$(DATE)
	docker tag gone/node:11-gcc gone/node:11-gcc-$(ARCH)-$(DATE)
	docker tag gone/node:11-gcc gone/node:11-gcc-$(ARCH)

tag-node-12:
	docker tag gone/node:12     gone/node:12-$(DATE)
	docker tag gone/node:12     gone/node:12-$(ARCH)-$(DATE)
	docker tag gone/node:12     gone/node:12-$(ARCH)
	docker tag gone/node:12-gcc gone/node:12-gcc-$(DATE)
	docker tag gone/node:12-gcc gone/node:12-gcc-$(ARCH)-$(DATE)
	docker tag gone/node:12-gcc gone/node:12-gcc-$(ARCH)

build:
	$(MAKE) prepare
	$(MAKE) build-marshall
	$(MAKE) build-php-5.6
	$(MAKE) build-php-7.0
	$(MAKE) build-php-7.1
	$(MAKE) build-php-7.2
	$(MAKE) build-php-7.3
	$(MAKE) build-node

push-marshall:
	docker push gone/marshall:latest
	docker push gone/marshall:$(DATE)
	docker push gone/marshall:$(ARCH)
	docker push gone/marshall:$(ARCH)-$(DATE)

push-core-5.6:
	docker push gone/php:core-5.6-$(ARCH)-$(DATE)
	docker push gone/php:core-5.6-$(ARCH)
	docker push gone/php:core-5.6

push-core-7.0:
	docker push gone/php:core-7.0-$(ARCH)-$(DATE)
	docker push gone/php:core-7.0-$(ARCH)
	docker push gone/php:core-7.0

push-core-7.1:
	docker push gone/php:core-7.1-$(ARCH)-$(DATE)
	docker push gone/php:core-7.1-$(ARCH)
	docker push gone/php:core-7.1

push-core-7.2:
	docker push gone/php:core-7.2-$(ARCH)-$(DATE)
	docker push gone/php:core-7.2-$(ARCH)
	docker push gone/php:core-7.2

push-core-7.3:
	docker push gone/php:core-7.3-$(ARCH)-$(DATE)
	docker push gone/php:core-7.3-$(ARCH)
	docker push gone/php:core-7.3

push-cli-5.6:
	docker push gone/php:cli-php5.6-$(ARCH)-$(DATE)
	docker push gone/php:cli-php5.6-$(ARCH)
	docker push gone/php:cli-php5.6

push-cli-7.0:
	docker push gone/php:cli-php7.0-$(ARCH)-$(DATE)
	docker push gone/php:cli-php7.0-$(ARCH)
	docker push gone/php:cli-php7.0

push-cli-7.1:
	docker push gone/php:cli-php7.1-$(ARCH)-$(DATE)
	docker push gone/php:cli-php7.1-$(ARCH)
	docker push gone/php:cli-php7.1

push-cli-7.2:
	docker push gone/php:cli-php7.2-$(ARCH)-$(DATE)
	docker push gone/php:cli-php7.2-$(ARCH)
	docker push gone/php:cli-php7.2

push-cli-7.3:
	docker push gone/php:cli-php7.3-$(ARCH)-$(DATE)
	docker push gone/php:cli-php7.3-$(ARCH)
	docker push gone/php:cli-php7.3

push-apache-5.6:
	docker push gone/php:apache-php5.6-$(ARCH)-$(DATE)
	docker push gone/php:apache-php5.6-$(ARCH)
	docker push gone/php:apache-php5.6

push-apache-7.0:
	docker push gone/php:apache-php7.0-$(ARCH)-$(DATE)
	docker push gone/php:apache-php7.0-$(ARCH)
	docker push gone/php:apache-php7.0

push-apache-7.1:
	docker push gone/php:apache-php7.1-$(ARCH)-$(DATE)
	docker push gone/php:apache-php7.1-$(ARCH)
	docker push gone/php:apache-php7.1

push-apache-7.2:
	docker push gone/php:apache-php7.2-$(ARCH)-$(DATE)
	docker push gone/php:apache-php7.2-$(ARCH)
	docker push gone/php:apache-php7.2

push-apache-7.3:
	docker push gone/php:apache-php7.3-$(ARCH)-$(DATE)
	docker push gone/php:apache-php7.3-$(ARCH)
	docker push gone/php:apache-php7.3

push-nginx-5.6:
	docker push gone/php:nginx-php5.6-$(ARCH)-$(DATE)
	docker push gone/php:nginx-php5.6-$(ARCH)
	docker push gone/php:nginx-php5.6
	
push-nginx-7.0:
	docker push gone/php:nginx-php7.0-$(ARCH)-$(DATE)
	docker push gone/php:nginx-php7.0-$(ARCH)
	docker push gone/php:nginx-php7.0
	
push-nginx-7.1:
	docker push gone/php:nginx-php7.1-$(ARCH)-$(DATE)
	docker push gone/php:nginx-php7.1-$(ARCH)
	docker push gone/php:nginx-php7.1
	
push-nginx-7.2:
	docker push gone/php:nginx-php7.2-$(ARCH)-$(DATE)
	docker push gone/php:nginx-php7.2-$(ARCH)
	docker push gone/php:nginx-php7.2
	
push-nginx-7.3:
	docker push gone/php:nginx-php7.3-$(ARCH)-$(DATE)
	docker push gone/php:nginx-php7.3-$(ARCH)
	docker push gone/php:nginx-php7.3

push-node-8:
	docker push gone/node:8
	docker push gone/node:8-$(DATE)
	docker push gone/node:8-$(ARCH)-$(DATE)
	docker push gone/node:8-$(ARCH)
	docker push gone/node:8-gcc
	docker push gone/node:8-gcc-$(DATE)
	docker push gone/node:8-gcc-$(ARCH)-$(DATE)
	docker push gone/node:8-gcc-$(ARCH)

push-node-10:
	docker push gone/node:10
	docker push gone/node:10-$(DATE)
	docker push gone/node:10-$(ARCH)-$(DATE)
	docker push gone/node:10-$(ARCH)
	docker push gone/node:10-gcc
	docker push gone/node:10-gcc-$(DATE)
	docker push gone/node:10-gcc-$(ARCH)-$(DATE)
	docker push gone/node:10-gcc-$(ARCH)

push-node-11:
	docker push gone/node:11
	docker push gone/node:11-$(DATE)
	docker push gone/node:11-$(ARCH)-$(DATE)
	docker push gone/node:11-$(ARCH)
	docker push gone/node:11-gcc
	docker push gone/node:11-gcc-$(DATE)
	docker push gone/node:11-gcc-$(ARCH)-$(DATE)
	docker push gone/node:11-gcc-$(ARCH)

push-node-12:
	docker push gone/node:12
	docker push gone/node:12-$(DATE)
	docker push gone/node:12-$(ARCH)-$(DATE)
	docker push gone/node:12-$(ARCH)
	docker push gone/node:12-gcc
	docker push gone/node:12-gcc-$(DATE)
	docker push gone/node:12-gcc-$(ARCH)-$(DATE)
	docker push gone/node:12-gcc-$(ARCH)

push-core: push-core-5.6 push-core-7.0 push-core-7.1 push-core-7.2 push-core-7.3
push-cli: push-cli-5.6 push-cli-7.0 push-cli-7.1 push-cli-7.2 push-cli-7.3
push-apache: push-apache-5.6 push-apache-7.0 push-apache-7.1 push-apache-7.2 push-apache-7.3
push-nginx: push-nginx-5.6 push-nginx-7.0 push-nginx-7.1 push-nginx-7.2 push-nginx-7.3
push-node: push-node-8 push-node-10 push-node-11 push-node-12
push: push-marshall push-core push-cli push-apache push-nginx push-node

aliases:
	docker pull gone/php:cli-php7.3
	docker pull gone/php:nginx-php7.3
	docker pull gone/php:apache-php7.3
	docker pull gone/node:12

	docker tag gone/php:cli-php7.3 gone/php:cli
	docker tag gone/php:nginx-php7.3 gone/php:nginx
	docker tag gone/php:apache-php7.3 gone/php:apache
	docker tag gone/node:12 gone/node:latest

	docker push gone/php:cli
	docker push gone/php:nginx
	docker push gone/php:apache
	docker push gone/node:latest

readme:
	./docs
	git add README.md
	git commit -m "Updated Readme with new docs" README.md
	git push

release: build readme push cleanup