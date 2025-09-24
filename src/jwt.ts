import * as jose from "jose";
import { JWTPayload } from "jose";
import "./index";

export interface GBMember {
  userId: number;
  userName: string;
  hasPremium: boolean;
  userEmail?: string;
  userAvatar?: string;
}

export interface GBPayload extends JWTPayload {
  "urn:gb:uid": number;
  "urn:gb:username": string;
  "urn:gb:premium": boolean;
  "urn:gb:email"?: string;
  "urn:gb:avatar"?: string;
}

export async function createJwtTokenRSA(
  message: GBMember,
  alg: string,
  pkcs8: string,
) {
  const privateKeyRSA = await jose.importPKCS8(pkcs8, alg);

  const issuer = process.env["URN_ISSUER"] || "no issuer";
  const audience = process.env["URN_AUDIENCE"] || "no aud";

  const payload = createGBPayload(message);

  const jwt = await new jose.SignJWT(payload)
    .setProtectedHeader({ alg })
    .setIssuedAt()
    .setIssuer(issuer)
    .setAudience(audience)
    .setExpirationTime("2h")
    .sign(privateKeyRSA);

  return jwt;
}

export async function verifyJwtTokenRSA(
  token: string,
  alg: string,
  spki: string,
) {
  const publicKeyRSA = await jose.importSPKI(spki, alg);

  const issuer = process.env["URN_ISSUER"] || "no issuer";
  const audience = process.env["URN_AUDIENCE"] || "no aud";
  const message = { issuer, audience };
  const { payload, protectedHeader } = await jose.jwtVerify(
    token,
    publicKeyRSA,
    message,
  );
  return { payload, protectedHeader };
}

export function createGBPayload(member: GBMember): GBPayload {
  let payload: GBPayload = {
    "urn:gb:uid": member.userId,
    "urn:gb:username": member.userName,
    "urn:gb:premium": member.hasPremium,
  };
  if (member.userEmail) {
    payload["urn:gb:email"] = member.userEmail;
  }
  if (member.userAvatar) {
    payload["urn:gb:avatar"] = member.userAvatar;
  }
  return payload;
}
