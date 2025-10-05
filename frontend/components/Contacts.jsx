"use client";

import { useSession } from "next-auth/react";
import { useEffect, useState } from "react";
import Loader from "./Loader";
import { CheckCircle, RadioButtonUnchecked } from "@mui/icons-material";
import { useRouter } from "next/navigation";

const Contacts = () => {
  const [loading, setLoading] = useState(true);
  const [contacts, setContacts] = useState([]);
  const [search, setSearch] = useState("");

  const { data: session } = useSession();
  const currentUser = session?.user;

  const getContacts = async () => {
    try {
      const res = await fetch(
        search !== "" ? `/api/users/searchContact/${search}` : "/api/users"
      );
      const data = await res.json();
      setContacts(data.filter((contact) => contact._id !== currentUser._id));
      setLoading(false);
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    if (currentUser) getContacts();
  }, [currentUser, search]);

  /* SELECT CONTACT */
  const [selectedContact, setSelectedContact] = useState(null);

  const handleSelect = (contact) => {
    setSelectedContact(contact);
  };

  const router = useRouter();

  /* CREATE CHAT */
  const createChat = async () => {
    const res = await fetch("/api/chats", {
      method: "POST",
      body: JSON.stringify({
        currentUserId: currentUser._id,
        members: [selectedContact._id],
        isGroup: false,
        name: "",
      }),
    });
    const chat = await res.json();

    if (res.ok) {
      router.push(`/chats/${chat._id}`);
    }
  };

  return loading ? (
    <Loader />
  ) : (
    <div className="create-chat-container">
      <input
        placeholder="Search contact..."
        className="input-search"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      <div className="contact-bar">
        <div className="contact-list">
          <p className="text-body-bold">Select Contact</p>

          <div className="flex flex-col flex-1 gap-5 overflow-y-scroll custom-scrollbar">
            {contacts.map((user, index) => (
              <div
                key={index}
                className="contact"
                onClick={() => handleSelect(user)}
              >
                {selectedContact === user ? (
                  <CheckCircle sx={{ color: "red" }} />
                ) : (
                  <RadioButtonUnchecked />
                )}
                <img
                  src={user.profileImage || "/assets/person.jpg"}
                  alt="profile"
                  className="profilePhoto"
                />
                <p className="text-base-bold">{user.username}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="create-chat">
          <button
            className="btn"
            onClick={createChat}
            disabled={!selectedContact}
          >
            START CHAT
          </button>
        </div>
      </div>
    </div>
  );
};

export default Contacts;