/**
 * Tests for Country Flags Helper
 */

const {
  getCountryCode,
  getFlagUrl,
  getAllCountryCodes,
  hasCountryCode,
} = require("../countryFlags");

describe("countryFlags", () => {
  describe("getCountryCode", () => {
    it("should return country code for valid country name", () => {
      const code = getCountryCode("United States");
      expect(code).toBeTruthy();
      expect(typeof code).toBe("string");
      expect(code).toBe("us");
    });

    it("should return empty string for invalid country name", () => {
      expect(getCountryCode("Invalid Country Name")).toBe("");
    });

    it("should return empty string for null input", () => {
      expect(getCountryCode(null)).toBe("");
    });

    it("should return empty string for undefined input", () => {
      expect(getCountryCode(undefined)).toBe("");
    });

    it("should return empty string for empty string input", () => {
      expect(getCountryCode("")).toBe("");
    });

    it("should return same country code for different case country names", () => {
      const code1 = getCountryCode("Japan");
      const code2 = getCountryCode("japan");
      expect(code1).toBe(code2);
    });
  });

  describe("getFlagUrl", () => {
    it("should return valid flag URL with default size", () => {
      const url = getFlagUrl("us");
      expect(url).toBe("https://flagcdn.com/w20/us.png");
    });

    it("should return valid flag URL with custom size", () => {
      const url = getFlagUrl("us", "w40");
      expect(url).toBe("https://flagcdn.com/w40/us.png");
    });

    it("should handle uppercase country codes", () => {
      const url = getFlagUrl("US", "w20");
      expect(url).toBe("https://flagcdn.com/w20/us.png");
    });

    it("should handle mixed case country codes", () => {
      const url = getFlagUrl("Us", "w20");
      expect(url).toBe("https://flagcdn.com/w20/us.png");
    });

    it("should return empty string for null country code", () => {
      expect(getFlagUrl(null)).toBe("");
    });

    it("should return empty string for undefined country code", () => {
      expect(getFlagUrl(undefined)).toBe("");
    });

    it("should return empty string for empty string country code", () => {
      expect(getFlagUrl("")).toBe("");
    });

    it("should support different size variants", () => {
      const sizes = [
        "w20",
        "w40",
        "w80",
        "w160",
        "w320",
        "w640",
        "w1280",
        "w2560",
      ];
      sizes.forEach((size) => {
        const url = getFlagUrl("jp", size);
        expect(url).toBe(`https://flagcdn.com/${size}/jp.png`);
      });
    });
  });

  describe("getAllCountryCodes", () => {
    it("should return an object", () => {
      const codes = getAllCountryCodes();
      expect(typeof codes).toBe("object");
      expect(codes).not.toBeNull();
    });

    it("should return non-empty object", () => {
      const codes = getAllCountryCodes();
      expect(Object.keys(codes).length).toBeGreaterThan(0);
    });

    it("should return object with string values", () => {
      const codes = getAllCountryCodes();
      const values = Object.values(codes);
      values.forEach((value) => {
        expect(typeof value).toBe("string");
      });
    });

    it("should return object with string keys", () => {
      const codes = getAllCountryCodes();
      const keys = Object.keys(codes);
      keys.forEach((key) => {
        expect(typeof key).toBe("string");
      });
    });
  });

  describe("hasCountryCode", () => {
    it("should return true for valid country name", () => {
      const allCodes = getAllCountryCodes();
      const firstCountry = Object.keys(allCodes)[0];
      expect(hasCountryCode(firstCountry)).toBe(true);
    });

    it("should return false for invalid country name", () => {
      expect(hasCountryCode("Invalid Country Name")).toBe(false);
    });

    it("should return false for null input", () => {
      expect(hasCountryCode(null)).toBe(false);
    });

    it("should return false for undefined input", () => {
      expect(hasCountryCode(undefined)).toBe(false);
    });

    it("should return false for empty string input", () => {
      expect(hasCountryCode("")).toBe(false);
    });

    it("should check all countries from getAllCountryCodes", () => {
      const allCodes = getAllCountryCodes();
      Object.keys(allCodes).forEach((countryName) => {
        expect(hasCountryCode(countryName)).toBe(true);
      });
    });
  });

  describe("Integration tests", () => {
    it("should work with getCountryCode and getFlagUrl together", () => {
      const allCodes = getAllCountryCodes();
      const firstCountry = Object.keys(allCodes)[0];
      const code = getCountryCode(firstCountry);
      const url = getFlagUrl(code);

      expect(code).toBeTruthy();
      expect(url).toMatch(/^https:\/\/flagcdn\.com\/w20\/[a-z]{2}\.png$/);
    });

    it("should have consistent behavior between getCountryCode and hasCountryCode", () => {
      const allCodes = getAllCountryCodes();
      Object.keys(allCodes).forEach((countryName) => {
        const hasCode = hasCountryCode(countryName);
        const code = getCountryCode(countryName);

        if (hasCode) {
          expect(code).toBeTruthy();
        } else {
          expect(code).toBe("");
        }
      });
    });

    it("should handle invalid country through full workflow", () => {
      const invalidCountry = "Non-existent Country";
      expect(hasCountryCode(invalidCountry)).toBe(false);
      expect(getCountryCode(invalidCountry)).toBe("");
      expect(getFlagUrl(getCountryCode(invalidCountry))).toBe("");
    });
  });
});
