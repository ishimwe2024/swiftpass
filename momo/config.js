import dotenv from 'dotenv';
dotenv.config();

export const baseUrl = process.env.BASE_URL || 'https://sandbox.momodeveloper.mtn.com';
export const secondaryKey = process.env.SECONDARY_KEY || 'b506576d564b4d82bd128f9c938a9c91';
// The other credentials (API_USER_REFERENCE_ID, API_KEY) are loaded from .env directly.