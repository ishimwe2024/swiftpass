import express from 'express';
import axios from 'axios';
import cors from 'cors';
import bodyParser from 'body-parser';
import { v4 as uuidv4 } from 'uuid';
import dotenv from 'dotenv';
import { baseUrl, secondaryKey } from './config.js';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(express.static('public'));

// ---------- Token Cache ----------
let cachedToken = null;
let tokenExpiry = 0;

async function getAccessToken() {
  if (cachedToken && Date.now() < tokenExpiry) {
    return cachedToken;
  }

  const userRef = process.env.API_USER_REFERENCE_ID;
  const apiKey = process.env.API_KEY;

  if (!userRef || !apiKey) {
    throw new Error('API_USER_REFERENCE_ID and API_KEY must be set in .env');
  }

  const response = await axios.post(`${baseUrl}/collection/token/`, {}, {
    headers: {
      'Authorization': `Basic ${Buffer.from(`${userRef}:${apiKey}`).toString('base64')}`,
      'Ocp-Apim-Subscription-Key': secondaryKey,
    },
  });

  cachedToken = response.data.access_token;
  // Expire 1 minute before actual expiry to be safe
  tokenExpiry = Date.now() + (response.data.expires_in - 60) * 1000;
  return cachedToken;
}

// ---------- Payment Initiation ----------
app.post('/process_payment', async (req, res) => {
  try {
    const { phoneNumber, amount } = req.body;

    // Basic validation
    if (!phoneNumber || !amount || isNaN(amount) || amount <= 0) {
      return res.status(400).json({ error: 'Invalid phone number or amount' });
    }

    const referenceId = uuidv4(); // transaction reference
    const token = await getAccessToken();

    const requestBody = {
      amount: amount,
      currency: 'EUR',
      externalId: uuidv4(),
      payer: {
        partyIdType: 'MSISDN',
        partyId: phoneNumber,
      },
      payerMessage: 'Payment for booking',
      payeeNote: 'Payment received',
    };

    await axios.post(`${baseUrl}/collection/v1_0/requesttopay`, requestBody, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Reference-Id': referenceId,
        'X-Target-Environment': 'sandbox',
        'Content-Type': 'application/json',
        'Ocp-Apim-Subscription-Key': secondaryKey,
      },
    });

    // Return the referenceId to the client
    res.status(202).json({
      message: 'Payment request accepted',
      referenceId: referenceId,
    });
  } catch (error) {
    console.error('Payment error:', error.response?.data || error.message);
    res.status(500).json({ error: 'Payment processing failed' });
  }
});

// ---------- Payment Status ----------
app.get('/payment_status/:referenceId', async (req, res) => {
  const { referenceId } = req.params;
  try {
    const token = await getAccessToken();
    const response = await axios.get(`${baseUrl}/collection/v1_0/requesttopay/${referenceId}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'X-Target-Environment': 'sandbox',
        'Ocp-Apim-Subscription-Key': secondaryKey,
      },
    });
    res.json(response.data);
  } catch (error) {
    console.error('Status check error:', error.response?.data || error.message);
    res.status(500).json({ error: 'Unable to fetch payment status' });
  }
});

// ---------- Callback (POST) ----------
app.post('/callback', express.json(), (req, res) => {
  console.log('📩 Payment callback received:', req.body);
  // Here you can update your database, notify via WebSocket, etc.
  res.sendStatus(200);
});

// ---------- Start Server ----------
app.listen(PORT, () => {
  console.log(`🚀 Server running on http://localhost:${PORT}`);
});