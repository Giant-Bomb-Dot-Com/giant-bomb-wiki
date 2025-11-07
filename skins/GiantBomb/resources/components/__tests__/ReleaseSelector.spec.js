const { mount } = require("@vue/test-utils");
const ReleaseSelector = require("../ReleaseSelector.vue");

describe("ReleaseSelector", () => {
  const mockReleases = [
    {
      name: "Desert Strike: Return to the Gulf",
      platform: "Genesis",
      region: "United States",
      releaseDate: "1992-02-18",
      rating: "ESRB E",
      resolutions: "480p",
      soundSystems: "Stereo",
      widescreenSupport: "No",
      displayName: "Genesis (United States)",
    },
    {
      name: "Desert Strike: Return to the Gulf",
      platform: "SNES",
      region: "United States",
      releaseDate: "1992-10-01",
      rating: "ESRB E",
      resolutions: "480p",
      soundSystems: "Stereo",
      widescreenSupport: "No",
      displayName: "SNES (United States)",
    },
    {
      name: "Desert Strike Advance",
      platform: "Game Boy Advance",
      region: "United States",
      releaseDate: "N/A",
      rating: "ESRB E",
      resolutions: "N/A",
      soundSystems: "N/A",
      widescreenSupport: "No",
      displayName: "Game Boy Advance (United States)",
    },
  ];

  const defaultProps = {
    releases: JSON.stringify(mockReleases),
  };

  describe("Initial Render", () => {
    it("renders the dropdown with releases", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const dropdown = wrapper.find(".release-selector__dropdown");
      expect(dropdown.exists()).toBe(true);

      const options = dropdown.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].text()).toBe("Genesis (United States)");
      expect(options[1].text()).toBe("SNES (United States)");
      expect(options[2].text()).toBe("Game Boy Advance (United States)");
    });

    it("renders the label", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const label = wrapper.find(".release-selector__label");
      expect(label.exists()).toBe(true);
      expect(label.text()).toBe("Select specific release");
    });

    it("displays the first release details by default", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const details = wrapper.findAll(".game-detail-item");
      expect(details).toHaveLength(5); // 5 detail fields

      // Check Release date
      expect(details[0].find(".game-detail-label").text()).toBe("Release date");
      expect(details[0].find(".game-detail-value").text()).toBe("1992-02-18");

      // Check Rating
      expect(details[1].find(".game-detail-label").text()).toBe("Rating");
      expect(details[1].find(".game-detail-value").text()).toBe("ESRB E");

      // Check Resolutions
      expect(details[2].find(".game-detail-label").text()).toBe("Resolutions");
      expect(details[2].find(".game-detail-value").text()).toBe("480p");

      // Check Surround sound
      expect(details[3].find(".game-detail-label").text()).toBe(
        "Surround sound"
      );
      expect(details[3].find(".game-detail-value").text()).toBe("Stereo");

      // Check Widescreen
      expect(details[4].find(".game-detail-label").text()).toBe("Widescreen");
      expect(details[4].find(".game-detail-value").text()).toBe("No");
    });
  });

  describe("Dropdown Selection", () => {
    it("updates details when selecting a different release", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const dropdown = wrapper.find(".release-selector__dropdown");

      // Select the second release (SNES)
      await dropdown.setValue(1);

      const details = wrapper.findAll(".game-detail-item");

      // Check updated Release date
      expect(details[0].find(".game-detail-value").text()).toBe("1992-10-01");

      // Rating should still be ESRB E
      expect(details[1].find(".game-detail-value").text()).toBe("ESRB E");
    });

    it("shows N/A values for releases with missing data", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const dropdown = wrapper.find(".release-selector__dropdown");

      // Select the third release (GBA with N/A values)
      await dropdown.setValue(2);

      const details = wrapper.findAll(".game-detail-item");

      // Check N/A values
      expect(details[0].find(".game-detail-value").text()).toBe("N/A");
      expect(details[2].find(".game-detail-value").text()).toBe("N/A");
      expect(details[3].find(".game-detail-value").text()).toBe("N/A");
    });
  });

  describe("Edge Cases", () => {
    it("handles empty releases array", async () => {
      const wrapper = mount(ReleaseSelector, {
        props: {
          releases: "[]",
        },
      });

      await wrapper.vm.$nextTick();

      const dropdown = wrapper.find(".release-selector__dropdown");
      const label = wrapper.find(".release-selector__label");
      const details = wrapper.find(".game-details");

      expect(dropdown.exists()).toBe(false);
      expect(label.exists()).toBe(false);
      expect(details.exists()).toBe(false);
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(ReleaseSelector, {
        props: {
          releases: "invalid json",
        },
      });

      await wrapper.vm.$nextTick();

      const dropdown = wrapper.find(".release-selector__dropdown");
      expect(dropdown.exists()).toBe(false);

      expect(consoleSpy).toHaveBeenCalledWith(
        "Failed to parse releases data:",
        expect.any(Error)
      );

      consoleSpy.mockRestore();
    });
  });
});
