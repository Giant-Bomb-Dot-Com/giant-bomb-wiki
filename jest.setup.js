// Jest setup file for Vue component tests

// Suppress console.error for jsdom navigation warnings
// These occur when components try to navigate (window.location.href = ...)
// since jsdom doesn't support actual navigation
const originalError = console.error;

beforeAll(() => {
  console.error = jest.fn((message) => {
    // Check if it's a jsdom navigation error
    if (
      typeof message === "object" &&
      message?.message?.includes("Not implemented: navigation")
    ) {
      return; // Suppress jsdom navigation errors
    }
    // Call original console.error for other errors
    originalError(message);
  });
});

afterAll(() => {
  console.error = originalError;
});
