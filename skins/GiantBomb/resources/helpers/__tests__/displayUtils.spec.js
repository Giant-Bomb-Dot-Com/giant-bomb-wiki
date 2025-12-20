/**
 * Tests for Display Utils Helper
 */

const { formatPlatforms } = require("../displayUtils");

describe("displayUtils", () => {
  describe("formatPlatforms", () => {
    it("should return empty string for null input", () => {
      expect(formatPlatforms(null)).toBe("");
    });

    it("should return empty string for undefined input", () => {
      expect(formatPlatforms(undefined)).toBe("");
    });

    it("should return empty string for empty array", () => {
      expect(formatPlatforms([])).toBe("");
    });

    it("should format single platform", () => {
      const platforms = [{ abbrev: "PC" }];
      expect(formatPlatforms(platforms)).toBe("PC");
    });

    it("should format two platforms", () => {
      const platforms = [{ abbrev: "PC" }, { abbrev: "PS5" }];
      expect(formatPlatforms(platforms)).toBe("PC, PS5");
    });

    it("should format exactly three platforms without 'more' suffix", () => {
      const platforms = [
        { abbrev: "PC" },
        { abbrev: "PS5" },
        { abbrev: "Xbox Series X" },
      ];
      expect(formatPlatforms(platforms)).toBe("PC, PS5, Xbox Series X");
    });

    it("should format four platforms with 'more' suffix", () => {
      const platforms = [
        { abbrev: "PC" },
        { abbrev: "PS5" },
        { abbrev: "Xbox Series X" },
        { abbrev: "Switch" },
      ];
      expect(formatPlatforms(platforms)).toBe("PC, PS5, Xbox Series X +1 more");
    });

    it("should format five platforms with correct 'more' count", () => {
      const platforms = [
        { abbrev: "PC" },
        { abbrev: "PS5" },
        { abbrev: "Xbox Series X" },
        { abbrev: "Switch" },
        { abbrev: "PS4" },
      ];
      expect(formatPlatforms(platforms)).toBe("PC, PS5, Xbox Series X +2 more");
    });

    it("should format many platforms correctly", () => {
      const platforms = [
        { abbrev: "PC" },
        { abbrev: "PS5" },
        { abbrev: "Xbox Series X" },
        { abbrev: "Switch" },
        { abbrev: "PS4" },
        { abbrev: "Xbox One" },
        { abbrev: "3DS" },
        { abbrev: "Vita" },
      ];
      expect(formatPlatforms(platforms)).toBe("PC, PS5, Xbox Series X +5 more");
    });

    it("should handle platforms with empty abbrev strings", () => {
      const platforms = [{ abbrev: "PC" }, { abbrev: "" }, { abbrev: "PS5" }];
      expect(formatPlatforms(platforms)).toBe("PC, PS5");
    });

    it("should handle platforms with missing abbrev property", () => {
      const platforms = [{ abbrev: "PC" }, {}, { abbrev: "PS5" }];
      expect(formatPlatforms(platforms)).toBe("PC, PS5");
    });

    it("should only show first three platforms regardless of total count", () => {
      const platforms = Array.from({ length: 10 }, (_, i) => ({
        abbrev: `Platform${i + 1}`,
      }));
      const result = formatPlatforms(platforms);
      expect(result).toBe("Platform1, Platform2, Platform3 +7 more");
      expect(result).not.toContain("Platform4");
    });

    it("should handle platforms with special characters in abbrev", () => {
      const platforms = [
        { abbrev: "PC (Steam)" },
        { abbrev: "PS5/PS4" },
        { abbrev: "Xbox Series X|S" },
      ];
      expect(formatPlatforms(platforms)).toBe(
        "PC (Steam), PS5/PS4, Xbox Series X|S",
      );
    });
  });
});
