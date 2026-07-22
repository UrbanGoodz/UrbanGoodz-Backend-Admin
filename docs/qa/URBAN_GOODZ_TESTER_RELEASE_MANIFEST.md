# Urban Goodz Final Tester Release Manifest

Build Timestamp: 2026-07-22 07:00:00
Live Server Environment: https://admin.urbangoodzdelivery.com
Live API Base URL: https://admin.urbangoodzdelivery.com/api/v1
Authoritative Deployed Backend SHA: 3037ce7e7db7741134fc52d222a580898f586bf7
Server Backup Archive: /home/urbakkej/backups/urban_goodz_deploy_20260722_074053
Queue Connection: sync

---

## 1. Customer Application (Urban Goodz Shopper)
- **Package Name**: `com.urbangoodz.customer`
- **Application Label**: `Urban Goodz Shopper`
- **Version Name**: `3.9.0`
- **Version Code**: `5`
- **Source SHA**: `663f4dba719250e86222578ee22e6b0e6f355a24`
- **API Base Config File**: `lib/util/app_constants.dart:43` (`https://admin.urbangoodzdelivery.com`)
- **Artifact Path**: `outputs/UrbanGoodz_Shopper_Tester_Final.apk`
- **File Size**: `129715356` bytes
- **SHA-256 Digest**: `9AB18912925FC28064085A0DFE28E6DC9A2B140C3DE6559F57C3894D38A2F924`
- **Live Device Check**: PASSED on `ZT42268MG6` (Startup, Config, Stores, Details, Sandbox Order)

## 2. Driver Application (Urban Goodz Driver)
- **Package Name**: `com.urbangoodz.driver`
- **Application Label**: `Urban Goodz Driver`
- **Version Name**: `3.9.1`
- **Version Code**: `7`
- **Source SHA**: `c633cec1e6389ca9ca3d3d334e9dcbe3e944b27d`
- **API Base Config File**: `lib/util/app_constants.dart:43` (`https://admin.urbangoodzdelivery.com`)
- **Artifact Path**: `outputs/UrbanGoodz_Driver_Tester_Final.apk`
- **File Size**: `58492844` bytes
- **SHA-256 Digest**: `3F22483A0C67AC7A001195190858A7D2DAC4689A96332B4B82010185DAC50C0E`
- **Live Device Check**: PASSED on `ZT42268MG6` (Startup, Auth, Profile, Jobs, Auth Header)

## 3. Vendor Application (Urban Goodz Vendor)
- **Package Name**: `com.urbangoodz.vendor`
- **Application Label**: `Urban Goodz Vendor`
- **Version Name**: `3.9.3`
- **Version Code**: `10`
- **Source SHA**: `d7d6678` (`c633cec1e6389ca9ca3d3d334e9dcbe3e944b27d`)
- **API Base Config File**: `lib/util/app_constants.dart:43` (`https://admin.urbangoodzdelivery.com`)
- **Artifact Path**: `outputs/UrbanGoodz_Vendor_Tester_Final.apk`
- **File Size**: `59865257` bytes
- **SHA-256 Digest**: `855E6F38B9CCCB5D62555F838C248286821F9703C9EA70A34C430564CA536696`
- **Signer Cert SHA-1**: `58:8E:35:8C:1C:18:61:05:85:CD:6F:18:34:54:D7:29:70:24:50:48`
- **Signer Cert SHA-256**: `F5:31:91:56:30:DB:B0:FC:1A:EC:9B:25:40:B7:3F:F8:43:8C:C3:3B:88:B4:52:F2:BD:97:51:EC:4B:59:A7:B5`
- **Live Device Check**: PASSED on `ZT42268MG6` (Startup, Auth, Profile, Order List, Product View)
