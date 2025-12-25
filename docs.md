# Kế Hoạch Xây Dựng Chat Real-time với Node.js và React

## Tổng quan dự án
Xây dựng ứng dụng chat real-time sử dụng Node.js (backend) và React (frontend) với Socket.io để truyền dữ liệu real-time.

## Flow phát triển dự án

### Phase 1: Chuẩn bị và Setup dự án

#### 1.1 Cấu trúc thư mục
```
chat-realtime/
├── client/          # React frontend
├── server/          # Node.js backend
├── shared/          # Shared types/utilities (tùy chọn)
└── docs/           # Tài liệu
```

#### 1.2 Setup Backend (Node.js + Express)
1. Khởi tạo project:
   ```bash
   mkdir server
   cd server
   npm init -y
   ```

2. Cài đặt dependencies:
   ```bash
   # Production dependencies
   npm install express socket.io cors dotenv mongoose bcryptjs jsonwebtoken express-rate-limit

   # Development dependencies
   npm install -D nodemon concurrently eslint prettier
   ```

3. Cấu trúc thư mục server:
   ```
   server/
   ├── src/
   │   ├── controllers/     # Xử lý logic business
   │   ├── models/         # Models database
   │   ├── routes/         # Định tuyến API
   │   ├── middleware/     # Middleware tùy chỉnh
   │   ├── socket/         # Xử lý Socket.io events
   │   ├── utils/          # Utility functions
   │   └── config/         # Cấu hình database
   ├── .env               # Environment variables
   ├── .gitignore
   ├── package.json
   └── server.js          # Entry point
   ```

#### 1.3 Setup Frontend (React với Vite)
1. Khởi tạo React app:
   ```bash
   npm create vite@latest client -- --template react
   cd client
   npm install
   ```

2. Cài đặt thêm dependencies:
   ```bash
   # Production dependencies
   npm install socket.io-client react-router-dom axios @emotion/react @emotion/styled react-icons

   # Development dependencies
   npm install -D eslint-plugin-react-hooks
   ```

3. Cấu trúc thư mục client:
   ```
   client/
   ├── src/
   │   ├── components/     # Components reusable
   │   │   ├── ui/         # UI components cơ bản
   │   │   ├── chat/       # Chat-related components
   │   │   └── auth/       # Authentication components
   │   ├── pages/          # Các trang chính
   │   ├── hooks/          # Custom hooks
   │   ├── services/       # API services
   │   ├── context/        # Context providers
   │   ├── utils/          # Utility functions
   │   ├── styles/         # Styles global
   │   └── assets/         # Images, icons, etc.
   ├── public/
   └── index.html
   ```

### Phase 2: Backend Implementation

#### 2.1 Setup Express Server cơ bản
```javascript
// server/server.js
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
require('dotenv').config();

const app = express();
const server = http.createServer(app);

// Socket.io configuration
const io = socketIo(server, {
  cors: {
    origin: process.env.CLIENT_URL || "http://localhost:3000",
    methods: ["GET", "POST"],
    credentials: true
  }
});

// Middleware
app.use(cors());
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

// Routes
app.get('/api', (req, res) => {
  res.json({
    message: 'Chat API Server v1.0.0',
    status: 'running',
    timestamp: new Date().toISOString()
  });
});

// Import routes
app.use('/api/auth', require('./src/routes/auth'));
app.use('/api/rooms', require('./src/routes/rooms'));
app.use('/api/messages', require('./src/routes/messages'));
app.use('/api/users', require('./src/routes/users'));

// Socket handler
require('./src/socket/socketHandler')(io);

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ message: 'Something went wrong!' });
});

const PORT = process.env.PORT || 5000;
server.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
  console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
});
```

#### 2.2 Socket.io Implementation
```javascript
// src/socket/socketHandler.js
const jwt = require('jsonwebtoken');
const User = require('../models/User');

const handleSocketConnection = (io) => {
  // Authentication middleware for sockets
  io.use(async (socket, next) => {
    try {
      const token = socket.handshake.auth.token;
      const decoded = jwt.verify(token, process.env.JWT_SECRET);
      const user = await User.findById(decoded.userId).select('-password');

      if (!user) {
        return next(new Error('User not found'));
      }

      socket.userId = user._id;
      socket.username = user.username;
      next();
    } catch (err) {
      next(new Error('Authentication error'));
    }
  });

  io.on('connection', (socket) => {
    console.log(`✅ User connected: ${socket.username} (${socket.id})`);

    // Update user online status
    User.findByIdAndUpdate(socket.userId, { isOnline: true }).exec();

    // Join room
    socket.on('join-room', async (roomId) => {
      try {
        socket.join(roomId);

        // Notify others in room
        socket.to(roomId).emit('user-joined', {
          userId: socket.userId,
          username: socket.username,
          socketId: socket.id
        });

        // Send current online users in room
        const sockets = await io.in(roomId).fetchSockets();
        const onlineUsers = sockets.map(s => ({
          userId: s.userId,
          username: s.username,
          socketId: s.id
        }));

        socket.emit('online-users', onlineUsers);
      } catch (error) {
        console.error('Error joining room:', error);
      }
    });

    // Leave room
    socket.on('leave-room', (roomId) => {
      socket.leave(roomId);
      socket.to(roomId).emit('user-left', {
        userId: socket.userId,
        username: socket.username
      });
    });

    // Send message
    socket.on('send-message', async (data) => {
      try {
        const { roomId, message, type = 'text' } = data;

        // Save message to database
        const Message = require('../models/Message');
        const messageDoc = new Message({
          content: message,
          sender: socket.userId,
          room: roomId,
          type: type
        });

        await messageDoc.save();
        await messageDoc.populate('sender', 'username avatar');

        // Broadcast to room
        io.to(roomId).emit('receive-message', messageDoc);
      } catch (error) {
        console.error('Error sending message:', error);
        socket.emit('message-error', { message: 'Failed to send message' });
      }
    });

    // Typing indicators
    socket.on('typing', (roomId) => {
      socket.to(roomId).emit('user-typing', {
        userId: socket.userId,
        username: socket.username
      });
    });

    socket.on('stop-typing', (roomId) => {
      socket.to(roomId).emit('user-stop-typing', socket.userId);
    });

    // Private message
    socket.on('send-private-message', async (data) => {
      try {
        const { recipientId, message } = data;

        // Create private room ID
        const roomId = [socket.userId, recipientId].sort().join('_');

        // Save message
        const Message = require('../models/Message');
        const messageDoc = new Message({
          content: message,
          sender: socket.userId,
          room: roomId,
          type: 'private',
          recipients: [socket.userId, recipientId]
        });

        await messageDoc.save();
        await messageDoc.populate('sender', 'username avatar');

        // Send to recipient if online
        const recipientSockets = await io.fetchSockets();
        const recipientSocket = recipientSockets.find(s => s.userId == recipientId);

        if (recipientSocket) {
          recipientSocket.emit('receive-private-message', messageDoc);
        }

        // Send back to sender
        socket.emit('receive-private-message', messageDoc);
      } catch (error) {
        console.error('Error sending private message:', error);
      }
    });

    // Disconnect
    socket.on('disconnect', async () => {
      console.log(`❌ User disconnected: ${socket.username} (${socket.id})`);

      // Update offline status
      await User.findByIdAndUpdate(socket.userId, {
        isOnline: false,
        lastSeen: new Date()
      }).exec();

      // Notify all rooms user was in
      const rooms = socket.rooms;
      rooms.forEach(room => {
        if (room !== socket.id) {
          socket.to(room).emit('user-left', {
            userId: socket.userId,
            username: socket.username
          });
        }
      });
    });
  });
};

module.exports = handleSocketConnection;
```

#### 2.3 Database Models
```javascript
// src/models/User.js
const mongoose = require('mongoose');
const bcrypt = require('bcryptjs');

const userSchema = new mongoose.Schema({
  username: {
    type: String,
    required: true,
    unique: true,
    trim: true,
    minlength: 3,
    maxlength: 30,
    match: /^[a-zA-Z0-9_]+$/
  },
  email: {
    type: String,
    required: true,
    unique: true,
    lowercase: true,
    trim: true
  },
  password: {
    type: String,
    required: true,
    minlength: 6
  },
  avatar: {
    type: String,
    default: ''
  },
  bio: {
    type: String,
    maxlength: 200,
    default: ''
  },
  isOnline: {
    type: Boolean,
    default: false
  },
  lastSeen: {
    type: Date,
    default: Date.now
  },
  friends: [{
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    addedAt: {
      type: Date,
      default: Date.now
    }
  }]
}, {
  timestamps: true
});

// Hash password before saving
userSchema.pre('save', async function(next) {
  if (!this.isModified('password')) return next();
  this.password = await bcrypt.hash(this.password, 12);
  next();
});

// Method to check password
userSchema.methods.comparePassword = async function(password) {
  return bcrypt.compare(password, this.password);
};

// Method to get public profile
userSchema.methods.toPublicJSON = function() {
  return {
    _id: this._id,
    username: this.username,
    email: this.email,
    avatar: this.avatar,
    bio: this.bio,
    isOnline: this.isOnline,
    lastSeen: this.lastSeen,
    createdAt: this.createdAt
  };
};

module.exports = mongoose.model('User', userSchema);
```

```javascript
// src/models/Room.js
const mongoose = require('mongoose');

const roomSchema = new mongoose.Schema({
  name: {
    type: String,
    required: true,
    trim: true,
    maxlength: 50
  },
  description: {
    type: String,
    maxlength: 200,
    default: ''
  },
  type: {
    type: String,
    enum: ['public', 'private', 'direct'],
    default: 'public'
  },
  createdBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  admins: [{
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  }],
  members: [{
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    joinedAt: {
      type: Date,
      default: Date.now
    },
    role: {
      type: String,
      enum: ['admin', 'moderator', 'member'],
      default: 'member'
    }
  }],
  lastMessage: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Message'
  },
  isArchived: {
    type: Boolean,
    default: false
  }
}, {
  timestamps: true
});

// Index for searching
roomSchema.index({ name: 'text', description: 'text' });

module.exports = mongoose.model('Room', roomSchema);
```

```javascript
// src/models/Message.js
const mongoose = require('mongoose');

const messageSchema = new mongoose.Schema({
  content: {
    type: String,
    required: true,
    trim: true,
    maxlength: 1000
  },
  sender: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  room: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Room',
    required: true
  },
  type: {
    type: String,
    enum: ['text', 'image', 'file', 'system'],
    default: 'text'
  },
  fileUrl: {
    type: String,
    default: ''
  },
  fileName: {
    type: String,
    default: ''
  },
  fileSize: {
    type: Number,
    default: 0
  },
  readBy: [{
    user: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    readAt: {
      type: Date,
      default: Date.now
    }
  }],
  replyTo: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Message'
  },
  reactions: [{
    emoji: String,
    users: [{
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    }]
  }],
  isEdited: {
    type: Boolean,
    default: false
  },
  editedAt: {
    type: Date
  },
  isDeleted: {
    type: Boolean,
    default: false
  },
  deletedAt: {
    type: Date
  }
}, {
  timestamps: true
});

// Index for querying messages
messageSchema.index({ room: 1, createdAt: -1 });
messageSchema.index({ sender: 1, room: 1 });

module.exports = mongoose.model('Message', messageSchema);
```

### Phase 3: Frontend Implementation

#### 3.1 Context Providers
```jsx
// src/context/AuthContext.jsx
import React, { createContext, useContext, useReducer, useEffect } from 'react';
import axios from 'axios';

const AuthContext = createContext();

const initialState = {
  isAuthenticated: false,
  user: null,
  token: localStorage.getItem('token'),
  loading: true,
  error: null
};

const authReducer = (state, action) => {
  switch (action.type) {
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        isAuthenticated: true,
        user: action.payload.user,
        token: action.payload.token,
        loading: false,
        error: null
      };
    case 'LOGIN_FAILURE':
      return {
        ...state,
        isAuthenticated: false,
        user: null,
        token: null,
        loading: false,
        error: action.payload
      };
    case 'LOGOUT':
      return {
        ...state,
        isAuthenticated: false,
        user: null,
        token: null,
        loading: false,
        error: null
      };
    case 'SET_LOADING':
      return {
        ...state,
        loading: action.payload
      };
    case 'UPDATE_USER':
      return {
        ...state,
        user: { ...state.user, ...action.payload }
      };
    default:
      return state;
  }
};

export const AuthProvider = ({ children }) => {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Configure axios defaults
  useEffect(() => {
    if (state.token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${state.token}`;
      localStorage.setItem('token', state.token);
    } else {
      delete axios.defaults.headers.common['Authorization'];
      localStorage.removeItem('token');
    }
  }, [state.token]);

  // Set base URL
  useEffect(() => {
    axios.defaults.baseURL = process.env.REACT_APP_API_URL || 'http://localhost:5000/api';
  }, []);

  const login = async (email, password) => {
    try {
      dispatch({ type: 'SET_LOADING', payload: true });
      const response = await axios.post('/auth/login', { email, password });

      dispatch({
        type: 'LOGIN_SUCCESS',
        payload: response.data
      });

      return response.data;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Login failed';
      dispatch({
        type: 'LOGIN_FAILURE',
        payload: errorMessage
      });
      throw error;
    }
  };

  const register = async (username, email, password) => {
    try {
      dispatch({ type: 'SET_LOADING', payload: true });
      const response = await axios.post('/auth/register', {
        username,
        email,
        password
      });

      dispatch({
        type: 'LOGIN_SUCCESS',
        payload: response.data
      });

      return response.data;
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Registration failed';
      throw new Error(errorMessage);
    }
  };

  const logout = () => {
    dispatch({ type: 'LOGOUT' });
  };

  const updateUser = (userData) => {
    dispatch({ type: 'UPDATE_USER', payload: userData });
  };

  const value = {
    ...state,
    login,
    register,
    logout,
    updateUser
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
```

```jsx
// src/context/SocketContext.jsx
import React, { createContext, useContext, useEffect, useState, useRef } from 'react';
import io from 'socket.io-client';
import { useAuth } from './AuthContext';

const SocketContext = createContext();

export const SocketProvider = ({ children }) => {
  const [socket, setSocket] = useState(null);
  const [connected, setConnected] = useState(false);
  const [onlineUsers, setOnlineUsers] = useState([]);
  const { token, isAuthenticated } = useAuth();
  const socketRef = useRef();

  useEffect(() => {
    if (isAuthenticated && token) {
      // Initialize socket connection
      socketRef.current = io(process.env.REACT_APP_SOCKET_URL || 'http://localhost:5000', {
        auth: {
          token
        },
        transports: ['websocket', 'polling']
      });

      // Connection events
      socketRef.current.on('connect', () => {
        console.log('Connected to server');
        setConnected(true);
        setSocket(socketRef.current);
      });

      socketRef.current.on('disconnect', () => {
        console.log('Disconnected from server');
        setConnected(false);
      });

      socketRef.current.on('connect_error', (error) => {
        console.error('Socket connection error:', error);
        setConnected(false);
      });

      // Custom events
      socketRef.current.on('receive-message', (message) => {
        // This will be handled by individual components
      });

      socketRef.current.on('user-typing', (data) => {
        // Handle typing indicator
      });

      socketRef.current.on('user-stop-typing', (userId) => {
        // Handle stop typing
      });

      socketRef.current.on('receive-private-message', (message) => {
        // Handle private message
      });

      // Cleanup on unmount
      return () => {
        if (socketRef.current) {
          socketRef.current.disconnect();
          socketRef.current = null;
          setSocket(null);
          setConnected(false);
        }
      };
    }
  }, [isAuthenticated, token]);

  const joinRoom = (roomId) => {
    if (socket && connected) {
      socket.emit('join-room', roomId);
    }
  };

  const leaveRoom = (roomId) => {
    if (socket && connected) {
      socket.emit('leave-room', roomId);
    }
  };

  const sendMessage = (roomId, message, type = 'text') => {
    if (socket && connected) {
      socket.emit('send-message', {
        roomId,
        message,
        type
      });
    }
  };

  const sendPrivateMessage = (recipientId, message) => {
    if (socket && connected) {
      socket.emit('send-private-message', {
        recipientId,
        message
      });
    }
  };

  const emitTyping = (roomId) => {
    if (socket && connected) {
      socket.emit('typing', roomId);
    }
  };

  const emitStopTyping = (roomId) => {
    if (socket && connected) {
      socket.emit('stop-typing', roomId);
    }
  };

  const value = {
    socket,
    connected,
    onlineUsers,
    joinRoom,
    leaveRoom,
    sendMessage,
    sendPrivateMessage,
    emitTyping,
    emitStopTyping
  };

  return (
    <SocketContext.Provider value={value}>
      {children}
    </SocketContext.Provider>
  );
};

export const useSocket = () => {
  const context = useContext(SocketContext);
  if (!context) {
    throw new Error('useSocket must be used within a SocketProvider');
  }
  return context;
};
```

#### 3.2 Main Chat Component
```jsx
// src/pages/Chat.jsx
import React, { useState, useEffect } from 'react';
import styled from '@emotion/styled';
import { useSocket } from '../context/SocketContext';
import { useAuth } from '../context/AuthContext';
import Sidebar from '../components/Sidebar';
import ChatWindow from '../components/ChatWindow';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const ChatContainer = styled.div`
  display: flex;
  height: 100vh;
  background-color: #f5f5f5;
`;

const MainContent = styled.div`
  flex: 1;
  display: flex;
  flex-direction: column;
`;

const WelcomeScreen = styled.div`
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;

  h1 {
    color: #333;
    margin-bottom: 1rem;
  }

  p {
    color: #666;
    max-width: 500px;
  }
`;

const Chat = () => {
  const [currentRoom, setCurrentRoom] = useState(null);
  const [currentChat, setCurrentChat] = useState(null); // Can be room or user
  const [rooms, setRooms] = useState([]);
  const [loading, setLoading] = useState(true);
  const { socket, connected } = useSocket();
  const { user } = useAuth();

  useEffect(() => {
    // Load user's rooms
    const loadRooms = async () => {
      try {
        const response = await axios.get('/rooms/my-rooms');
        setRooms(response.data);
      } catch (error) {
        console.error('Error loading rooms:', error);
      } finally {
        setLoading(false);
      }
    };

    if (user) {
      loadRooms();
    }
  }, [user]);

  // Listen for new messages
  useEffect(() => {
    if (socket) {
      socket.on('receive-message', (message) => {
        if (currentRoom && message.room === currentRoom._id) {
          // Message will be handled by ChatWindow component
        }

        // Update room's last message
        setRooms(prevRooms =>
          prevRooms.map(room =>
            room._id === message.room
              ? { ...room, lastMessage: message, lastActivity: new Date() }
              : room
          )
        );
      });

      return () => {
        socket.off('receive-message');
      };
    }
  }, [socket, currentRoom]);

  const handleRoomSelect = (room) => {
    setCurrentRoom(room);
    setCurrentChat(room);
    if (socket) {
      joinRoom(room._id);
    }
  };

  const handleUserSelect = (selectedUser) => {
    // For direct messages
    setCurrentRoom(null);
    setCurrentChat({
      _id: selectedUser._id,
      name: selectedUser.username,
      type: 'direct',
      user: selectedUser
    });
  };

  const handleLeaveRoom = () => {
    if (currentRoom && socket) {
      leaveRoom(currentRoom._id);
    }
    setCurrentRoom(null);
    setCurrentChat(null);
  };

  if (loading) {
    return <LoadingSpinner />;
  }

  if (!connected) {
    return (
      <ChatContainer>
        <div style={{
          flex: 1,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexDirection: 'column'
        }}>
          <h2>Kết nối thất bại</h2>
          <p>Không thể kết nối đến server. Vui lòng thử lại sau.</p>
        </div>
      </ChatContainer>
    );
  }

  return (
    <ChatContainer>
      <Sidebar
        rooms={rooms}
        currentRoom={currentRoom}
        currentChat={currentChat}
        onRoomSelect={handleRoomSelect}
        onUserSelect={handleUserSelect}
      />
      <MainContent>
        {currentChat ? (
          <ChatWindow
            chat={currentChat}
            onLeaveChat={handleLeaveRoom}
          />
        ) : (
          <WelcomeScreen>
            <h1>Chào mừng đến với Chat App</h1>
            <p>Chọn một phòng chat hoặc người dùng để bắt đầu trò chuyện</p>
          </WelcomeScreen>
        )}
      </MainContent>
    </ChatContainer>
  );
};

export default Chat;
```

### Phase 4: Công nghệ và Libraries cần thiết

#### Backend Dependencies:
```json
{
  "dependencies": {
    "express": "^4.18.2",
    "socket.io": "^4.7.2",
    "mongoose": "^7.5.0",
    "bcryptjs": "^2.4.3",
    "jsonwebtoken": "^9.0.2",
    "cors": "^2.8.5",
    "dotenv": "^16.3.1",
    "express-rate-limit": "^6.10.0",
    "helmet": "^7.0.0",
    "express-validator": "^7.0.1",
    "multer": "^1.4.5",
    "cloudinary": "^1.40.0",
    "nodemailer": "^6.9.4"
  },
  "devDependencies": {
    "nodemon": "^3.0.1",
    "concurrently": "^8.2.0",
    "eslint": "^8.47.0",
    "prettier": "^3.0.2"
  }
}
```

#### Frontend Dependencies:
```json
{
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "react-router-dom": "^6.15.0",
    "socket.io-client": "^4.7.2",
    "axios": "^1.5.0",
    "@emotion/react": "^11.11.1",
    "@emotion/styled": "^11.11.0",
    "react-icons": "^4.10.1",
    "react-hot-toast": "^2.4.1",
    "react-infinite-scroll-component": "^6.1.0",
    "react-timeago": "^7.1.0",
    "emoji-picker-react": "^4.4.9",
    "zustand": "^4.4.1"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.0.4",
    "eslint": "^8.47.0",
    "eslint-plugin-react": "^7.33.2",
    "eslint-plugin-react-hooks": "^4.6.0",
    "prettier": "^3.0.2"
  }
}
```

### Phase 5: Features chi tiết cần implement

#### 5.1 Authentication Features:
- [ ] Đăng ký tài khoản với validation
- [ ] Login với remember me
- [ ] Reset password qua email
- [ ] Change password
- [ ] Profile management
- [ ] Avatar upload

#### 5.2 Chat Features:
- [ ] Real-time messaging
- [ ] Multiple chat rooms
- [ ] Direct messages
- [ ] Message history
- [ ] Typing indicators
- [ ] Online/offline status
- [ ] Read receipts
- [ ] Message reactions
- [ ] Message editing/deletion
- [ ] File/image sharing
- [ ] Emoji picker

#### 5.3 Room Features:
- [ ] Create public/private rooms
- [ ] Room settings
- [ ] Add/remove members
- [ ] Admin controls
- [ ] Room categories
- [ ] Search rooms
- [ ] Room invitations

#### 5.4 UI/UX Features:
- [ ] Responsive design (Mobile/Desktop)
- [ ] Dark/Light theme
- [ ] Customizable profiles
- [ ] Notification settings
- [ ] Sound notifications
- [ ] Search messages
- [ ] Keyboard shortcuts
- [ ] Drag & drop files

### Phase 6: Lộ trình triển khai chi tiết

#### Tuần 1: Foundation
- Day 1-2: Project setup, folder structure, package installation
- Day 3-4: Basic Express server with Socket.io
- Day 5-6: MongoDB setup and basic models
- Day 7: Test server connection

#### Tuần 2: Authentication
- Day 1-2: User model and authentication API
- Day 3-4: JWT implementation
- Day 5-6: Login/Register frontend
- Day 7: Protected routes testing

#### Tuần 3: Basic Chat
- Day 1-2: Room creation and management
- Day 3-4: Real-time messaging
- Day 5-6: Message persistence
- Day 7: UI components for chat

#### Tuần 4: Enhanced Features
- Day 1-2: Typing indicators, online status
- Day 3-4: Direct messages
- Day 5-6: File/image upload
- Day 7: Testing and bug fixes

#### Tuần 5: UI/UX Polish
- Day 1-2: Responsive design
- Day 3-4: Dark mode implementation
- Day 5-6: Animations and transitions
- Day 7: Performance optimization

#### Tuần 6: Advanced Features
- Day 1-2: Message reactions
- Day 3-4: Search functionality
- Day 5-6: Notifications system
- Day 7: Final testing

#### Tuần 7: Deployment
- Day 1-2: Production configuration
- Day 3-4: Backend deployment
- Day 5-6: Frontend deployment
- Day 7: Documentation and monitoring

### Phase 7: Environment Variables Setup

#### Backend (.env):
```env
NODE_ENV=development
PORT=5000
MONGODB_URI=mongodb://localhost:27017/chatapp
JWT_SECRET=your_jwt_secret_key_here
JWT_EXPIRE=7d
CLIENT_URL=http://localhost:3000
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USER=your_email@gmail.com
EMAIL_PASS=your_app_password
```

#### Frontend (.env):
```env
VITE_API_URL=http://localhost:5000/api
VITE_SOCKET_URL=http://localhost:5000
VITE_APP_NAME=Chat App
VITE_APP_VERSION=1.0.0
```

### Phase 8: Testing Strategy

#### Backend Testing:
- Unit tests with Jest
- API testing with Supertest
- Socket.io event testing
- Database operations testing

#### Frontend Testing:
- Component testing with React Testing Library
- Integration testing
- E2E testing with Cypress
- Socket connection testing

### Phase 9: Deployment Checklist

#### Production Setup:
- [ ] Set up MongoDB Atlas/Cloud database
- [ ] Configure environment variables
- [ ] Enable HTTPS/SSL
- [ ] Set up reverse proxy (Nginx)
- [ ] Configure CORS properly
- [ ] Set up logging
- [ ] Monitor performance
- [ ] Set up backups
- [ ] Error tracking (Sentry)

#### Security Checklist:
- [ ] Input validation
- [ ] Rate limiting
- [ ] XSS protection
- [ ] SQL injection prevention
- [ ] File upload restrictions
- [ ] Secure cookies
- [ ] CSRF protection

## Tips và Best Practices

1. **Start with MVP**: Focus on core features first
2. **Modular Architecture**: Keep components reusable
3. **Error Boundaries**: Handle errors gracefully
4. **Performance**: Implement pagination for messages
5. **Security**: Always validate and sanitize inputs
6. **Testing**: Write tests as you build
7. **Documentation**: Document your code
8. **Version Control**: Use Git properly with meaningful commits
9. **CI/CD**: Set up automated testing and deployment
10. **Monitoring**: Track application performance

Chúc bạn xây dựng thành công dự án chat real-time!