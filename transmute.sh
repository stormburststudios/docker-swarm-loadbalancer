#!/usr/bin/env bash
DEFAULT=ubuntu:bionic
TARGET=$1
case $TARGET in
    arm64v8)
        TARGET_IMAGE=arm64v8/ubuntu
        QEMU=aarch64
        ;;
    *)
        echo "Target not valid: $TARGET"
        exit 1
esac

echo "Transmuting $DEFAULT TO $TARGET_IMAGE"
./gen-transmute.sh $TARGET
cp Dockerfile Dockerfile.${TARGET}
sed -i "s|$DEFAULT|$TARGET_IMAGE|" Dockerfile.${TARGET}

#if [[ ! -f qemu-user-static.deb ]]; then
#    echo "Downloading qemu"
#    wget \
#        -O qemu-user-static.deb \
#        http://ftp.us.debian.org/debian/pool/main/q/qemu/qemu-user-static_4.2-3_amd64.deb
#fi

#if [[ ! -f qemu-${QEMU}-static ]]; then
#    echo "Unpacking qemu-${QEMU}-static"
#    dpkg-deb --fsys-tarfile qemu-user-static.deb \
#        | tar xvf - ./usr/bin/qemu-${QEMU}-static \
#        > qemu-${QEMU}-static
#    chmod +x qemu-${QEMU}-static
#fi

echo "Enabling qemu multiach"
docker run --rm --privileged multiarch/qemu-user-static --reset -p yes

echo "Saved as Dockerfile.${TARGET}"

head -18 Dockerfile.${TARGET}

docker build -f Dockerfile.${TARGET} -t marshall-${TARGET} --target=marshall .
