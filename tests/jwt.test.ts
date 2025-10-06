import * as dotenv from "dotenv";
import { expect, test } from "vitest";
import * as jose from "jose";
import { decode } from "jose/base64url";
import {
  GBMember,
  GBPayload,
  createJwtTokenRSA,
  verifyJwtTokenRSA,
  createGBPayload,
} from "../src/jwt.ts";

const pkcs8 = `-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC0WAljhnm15B0k
Qc70lqh8uq4rkWlaVa58fXOOUXYApoyizaEq7pMlrb7rWhVFN1JSEjTneg0BHR/F
U914h5/W6vFWN/4LYQis+3wnf6tlpcipR3i4EfDnhLFsF+mSJwYTwul9iClnPIm8
4KMUhOy8r8kjibqQp5/WK2cQ7G9jG7TF7bRgzrtTnZ5/mixtmdWGTrk68sJIzITQ
X2aRloKba1SsD9Xwt4+RDMJqpxoSGel5PfeK1KVx/7H8e/8ph0pzZacoQBkgMQZZ
a22NzYN2BC5nXuHdPEw2qtFI2VRWqaD0fGNxRbnpiwlVi0KYF7LXofbmJ0fPTbU8
SZyalolTAgMBAAECggEAAUAtDH4lwO+sjhBQxZx33amTxKSVBPXcKGvcTcvd1CJT
jlt7tycBaTz7unkc9aZtETmkGUjc5zmS+1TaD9hs8NofQ1hPEDgjjcOOuF1nzGQq
cJYYzbn+IDTo4BWIXqWboq5y3RaBJwCh+efa6a0EUUiszezKGO+4qY0SgWkKWrcW
9A/ibhp5zTpqM0B7PVcLLNdhAHAo3g4i3KVFO36+7gw4JebbwXWRju+gG/+nQW2d
cGJNmG/dN5e8cxjjyUbl5FptptnVdb1gVGJLyxnTuVt88U4BZ5r48JOXQ0TuPCWW
+DqU/7YtolDyG5qZnUsNWnHBxtVkWxVnJ10y5cZRAQKBgQD9SDMXUapIP3xRQ5Oy
OAmUnkNj38H9HJh2fw6jWUlyYeBGr2iJU/JbY+7j815nY4mHu1E0/V8z4jjgQcxC
JeFGSX6EMlocSmM5GECB/181DWtQLKxCM95fjeqgWr5ruH8IAFa6CF8itjTgrjY2
vnntQolVRzMAXNf1lAinAnrNAQKBgQC2R3dPDhGiRvualpt2pg4h/fsWX+Mmfrfn
k2xuTN9FSYMPC5N/IGPAJJB3UfYJqLkC1XH7TmWV6ny6KpVUt8loAAqecCIUu2xR
wTzYLpwHVZvMCkl1Wtyht/EA2bgOB+AumbcioEge8sx8RO1IvexDeN8N/y0Pss6c
BY5CflwSUwKBgQCbO/AMX8IU68we5mMUfOHqU6GMCe0UW71aCv+GwEeSskhKKmHQ
oUHOH46f6V430brOFNFtv8jkvMcNM2akOCE8+fhvv4iZBEKSELogssrAckwOZILj
RHULbaiaxWMmFHrPBZ4iANWGKdR4zG1v2ghSkVAoky3AECdQXX18Frn7AQKBgQCD
NLIWv6PF0Z5uShahsyndIfrIwkC5huFN2fpk8wSL1Tx3affDvEbRGgC7Qs39aeuy
tH2VTXsmDGG3h8tx7dEWAWGjZkfB8J8pwhTP78z0IpVAq+7wgHTsG4FpAU7RGq4u
HQuL6x++1zqGAP9kKwGUF79HTfTbpfO+rukVx+rybQKBgBKYc3oOJY439OEIx14F
kXlqX7mAQ7Fgxfhz2/ju0mDqcfdFgJpSCk2B+68fkbIumRbODcFOn6hCSRDGHyRG
+NvRViJonS6EB1DplOXi7cBZxlzui1x2AZB5ZpRO305B6FD5rXyk1KJq1nRh75zo
3lZRwE0MOK5/SdstB2iRyZ7z
-----END PRIVATE KEY-----`;

const spki = `-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtFgJY4Z5teQdJEHO9Jao
fLquK5FpWlWufH1zjlF2AKaMos2hKu6TJa2+61oVRTdSUhI053oNAR0fxVPdeIef
1urxVjf+C2EIrPt8J3+rZaXIqUd4uBHw54SxbBfpkicGE8LpfYgpZzyJvOCjFITs
vK/JI4m6kKef1itnEOxvYxu0xe20YM67U52ef5osbZnVhk65OvLCSMyE0F9mkZaC
m2tUrA/V8LePkQzCaqcaEhnpeT33itSlcf+x/Hv/KYdKc2WnKEAZIDEGWWttjc2D
dgQuZ17h3TxMNqrRSNlUVqmg9HxjcUW56YsJVYtCmBey16H25idHz021PEmcmpaJ
UwIDAQAB
-----END PUBLIC KEY-----`;

test("create signed JWT (RS256) and verify", async () => {
  dotenv.config();
  const userId = 123;
  const userName = "test-user-name";
  const premium = true;
  const userEmail = "test-user@example.club";
  const alg = "RS256";

  const member: GBMember = { userId, userName, userEmail, hasPremium: premium };

  createJwtTokenRSA(member, alg, pkcs8).then((value) => {
    const jwtToken = value;
    expect(jwtToken).not.toBe(null);
    expect(jwtToken).toBeTypeOf("string");

    const decodedPayload = jose.decodeJwt(jwtToken);

    verifyJwtTokenRSA(jwtToken, alg, spki).then((value) => {
      const { payload, protectedHeader } = value;
      expect(payload).toStrictEqual(decodedPayload);
    });
  });
});

test("dotenv loads URN configs", () => {
  dotenv.config(); // need to load test .env
  expect(process.env["URN_ISSUER"]).toBeDefined();
  expect(process.env["URN_ISSUER"]).toEqual(expect.stringMatching(/dev$/));
  expect(process.env["URN_AUDIENCE"]).toBeDefined();
  expect(process.env["URN_AUDIENCE"]).toEqual(expect.stringMatching(/dev$/));
});

test("create a GBPayload message from a GBMember", () => {
  const userId = 123;
  const userName = "test-user-name";
  const premium = true;
  const userEmail = "test-user@example.club";

  const member: GBMember = { userId, userName, userEmail, hasPremium: premium };
  const payload = createGBPayload(member);

  expect(payload).toStrictEqual({
    "urn:gb:uid": userId,
    "urn:gb:username": userName,
    "urn:gb:premium": premium,
    "urn:gb:email": userEmail,
    // no urn:gb:avatar, because it wasn't passed
  });
});
