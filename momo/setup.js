import axios from 'axios';
import { v4 as uuidv4 } from 'uuid';
import { baseUrl, secondaryKey } from './config.js';

const referenceId = uuidv4();

async function setup() {
  try {
    // 1. Create API user
    await axios.post(`${baseUrl}/v1_0/apiuser`, {
      providerCallbackHost: 'http://localhost:3000/callback' // change to your public URL in production
    }, {
      headers: {
        'X-Reference-Id': referenceId,
        'Ocp-Apim-Subscription-Key': secondaryKey,
        'Content-Type': 'application/json',
      },
    });

    // 2. Get API key
    const { data } = await axios.post(`${baseUrl}/v1_0/apiuser/${referenceId}/apikey`, {}, {
      headers: {
        'Ocp-Apim-Subscription-Key': secondaryKey,
      },
    });

    console.log('✅ API User created successfully!');
    console.log('API_USER_REFERENCE_ID=', referenceId);
    console.log('API_KEY=', data.apiKey);
    console.log('\nAdd these to your .env file.');
  } catch (error) {
    console.error('Setup failed:', error.response?.data || error.message);
  }
}

setup();