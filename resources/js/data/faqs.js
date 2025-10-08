import { ref } from "vue";

export const faqs = ref({
  "General Information": [
    {
      question: "Where is District Smiles Dental Center located?",
      answer:
        "Our clinic is located at 391 Odonas bldg F. Roxas street corner 10th Avenue Gracepark West, 1406 Caloocan, Philippines. You may also find us on Waze and Google maps for easy directions.",
      open: false,
    },
    {
      question: "How can I contact District Smiles Dental Center?",
      answer:
        "You can contact District Smiles Dental Center by email at districtsmiles@gmail.com or by phone at 0915 533 5086",
      open: false,
    },
    {
      question: "What are your clinic hours?",
      answer:
        "We are open Tuesday to Saturday, 10:00 AM to 6:00 PM. Closed on Monday and Sundays.",
      open: false,
    },
    {
      question: "What Payment methods do you accept?",
      answer:
        "We currently accept cash and through Gcash payments at the clinic. Additional payments options may be introduced in the future.",
      open: false,
    },
  ],
  Appointment: [
    {
      question: "Do you accept walk-in patients?",
      answer: "Yes, but we highly recommend booking an appointment in advance.",
      open: false,
    },
    {
      question: "How can I schedule an appointment?",
      answer:
        "You can schedule through our website, Facebook page, or by calling our clinic directly.",
      open: false,
    },
    {
      question: "Where can I see my appointment details?",
      answer: "After booking, we will send your appointment details via SMS or email.",
      open: false,
    },
    {
      question: "Can I reschedule or cancel my appointment?",
      answer:
        "Yes, just contact us at least 24 hours before your scheduled appointment.",
      open: false,
    },
  ],
  "Dental Services": [
    {
      question: "How long does a dental procedure usually take?",
      answer:
        "Cleaning usually take 30-45 minutes, while more complex procedures like root canal or surgeries may require additional time.",
      open: false,
    },
    {
      question: "How often should I visit the dentist?",
      answer: "A check-up and cleaning every six months is recommended.",
      open: false,
    },
    {
      question: "Do you offer emergency dental services?",
      answer:
        "Yes, we handle urgent cases such as severe tooth aches, broken teeth, and oral surgeries.",
      open: false,
    },
    {
      question: "Do you provide cosmetic and dentistry services?",
      answer:
        "Yes, we offer veeners, teeth whitening, and smile makeover options.",
      open: false,
    },
    {
      question: "What services do you offer?",
      answer:
        "Oral prophylaxis (cleaning), Restoration (pasta), Oral Surgery (wisdom tooth removal), Orthodontics (braces), Endodontics (root canal treatment), Prosthodontics (dentures), Cosmetics (veneers etc.) Visit Our Services Page for more information.",
      open: false,
    },
    {
      question: "Are X-rays safe?",
      answer:
        "Yes, dental X-rays use minimal radiation and are safe for both children and adults.",
      open: false,
    },
    {
      question: "Is teeth cleaning the same as teeth whitening?",
      answer:
        "No. Cleaning removes plaque and tartar, while whitening improves tooth color and brightness.",
      open: false,
    },
    {
      question: "How much the services cost?",
      answer:
        "Prices vary depending on the treatment. We offer consultations to provide accurate estimates before proceeding.",
      open: false,
    },
  ],
});