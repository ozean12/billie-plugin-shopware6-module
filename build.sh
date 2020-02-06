mkdir -p build/
tar --exclude-from=.release_exclude  -czf build/dist.tar.gz .
mkdir -p build/dist/BilliePayment/
tar -xzf build/dist.tar.gz -C build/dist/BilliePayment
rm -rf build/dist.tar.gz
cd build/dist
zip -r BilliePayment.zip BilliePayment
